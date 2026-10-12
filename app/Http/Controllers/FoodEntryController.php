<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFoodEntryRequest;
use App\Http\Requests\StoreFoodPhotoRequest;
use App\Models\FoodEntry;
use App\Models\User;
use App\Services\CalorieEstimationException;
use App\Services\NutritionAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class FoodEntryController extends Controller
{
    public function store(StoreFoodEntryRequest $request, NutritionAnalyzer $analyzer): RedirectResponse
    {
        $validated = $request->validated();
        $date = $validated['consumed_on'] ?? today()->toDateString();

        try {
            $analysis = $analyzer->analyze($validated['description']);
        } catch (CalorieEstimationException $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return $this->saveAndRedirect($request->user(), $analysis, $date);
    }

    public function storePhoto(StoreFoodPhotoRequest $request, NutritionAnalyzer $analyzer): RedirectResponse
    {
        $validated = $request->validated();
        $date = $validated['consumed_on'] ?? today()->toDateString();

        try {
            [$base64, $mime] = $this->prepareImage($request->file('photo'));
            $analysis = $analyzer->analyzeImage($base64, $mime);
        } catch (CalorieEstimationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->saveAndRedirect($request->user(), $analysis, $date, '📷 ');
    }

    public function destroy(FoodEntry $foodEntry): RedirectResponse
    {
        Gate::authorize('delete', $foodEntry);

        $date = $foodEntry->consumed_on->toDateString();
        $foodEntry->delete();

        return redirect()
            ->route('dashboard', ['date' => $date])
            ->with('success', 'Entry removed.');
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function saveAndRedirect(User $user, array $analysis, string $date, string $prefix = ''): RedirectResponse
    {
        $entry = $user->foodEntries()->create([
            ...$analysis,
            'consumed_on' => $date,
        ]);

        return redirect()
            ->route('dashboard', ['date' => $date])
            ->with('success', "{$prefix}Added {$entry->name} — {$entry->calories} kcal.");
    }

    /**
     * Downscale and re-encode an uploaded photo to a compact JPEG (base64),
     * keeping the payload small enough for the vision API.
     *
     * @return array{0: string, 1: string} [base64, mimeType]
     */
    private function prepareImage(UploadedFile $file): array
    {
        $raw = (string) file_get_contents($file->getRealPath());

        if (! extension_loaded('gd')) {
            // No image library — send the original bytes as-is.
            return [base64_encode($raw), $file->getMimeType() ?: 'image/jpeg'];
        }

        $image = @imagecreatefromstring($raw);

        if ($image === false) {
            throw new CalorieEstimationException('That image could not be read. Try a different photo.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $max = 1024;

        if (max($width, $height) > $max) {
            $scale = $max / max($width, $height);
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));

            if ($resized !== false) {
                imagedestroy($image);
                $image = $resized;
            }
        }

        // Rotate after downscaling so a second full-size copy is never held in memory.
        $image = $this->applyExifOrientation($image, $file);

        ob_start();
        imagejpeg($image, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        return [base64_encode($jpeg), 'image/jpeg'];
    }

    /**
     * Rotate the image to match its EXIF orientation (phone photos).
     *
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private function applyExifOrientation($image, UploadedFile $file)
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = $exif['Orientation'] ?? 0;

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);

            if ($rotated !== false) {
                imagedestroy($image);

                return $rotated;
            }
        }

        return $image;
    }
}
