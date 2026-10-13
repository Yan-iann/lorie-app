<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWaterLogRequest;
use App\Models\WaterLog;
use App\Support\LorieCoach;
use App\Support\WaterCalculator;
use App\Support\WaterDay;
use App\Support\WeekSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;

class WaterLogController extends Controller
{
    public function __construct(private readonly LorieCoach $lorie) {}

    public function store(StoreWaterLogRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $date = $validated['consumed_on'] ?? today()->toDateString();
        $container = WaterCalculator::CONTAINERS[$validated['container']];

        $request->user()->waterLogs()->create([
            'amount_ml' => $container['ml'],
            'container' => $validated['container'],
            'consumed_on' => $date,
        ]);

        $day = WaterDay::for($request->user(), $date);
        $message = 'Added '.WaterCalculator::format($container['ml']).' of water.';

        // Say so once, on the drink that takes them over the line.
        if ($day->goalReached() && $day->totalMl() - $container['ml'] < $day->goalMl) {
            $message .= ' You hit your water goal!';
        }

        return $this->respond($request, $day, $message);
    }

    public function destroy(Request $request, WaterLog $waterLog): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $waterLog);

        $date = $waterLog->consumed_on->toDateString();
        $amount = $waterLog->amount_ml;
        $waterLog->delete();

        return $this->respond($request, WaterDay::for($request->user(), $date), 'Removed '.WaterCalculator::format($amount).' of water.');
    }

    /**
     * Taps are sent in the background (see app.js) and get the redrawn water
     * parts of the dashboard, a toast and Lorie's updated line back; a plain
     * form post gets the usual redirect.
     */
    private function respond(Request $request, WaterDay $day, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'parts' => Blade::render(
                    '<x-water-gauge :day="$day" /><x-water-schedule :day="$day" /><x-water-controls :day="$day" /><x-week-strip :week="$week" />',
                    ['day' => $day, 'week' => WeekSummary::for($request->user(), $day->date)],
                ),
                'toast' => Blade::render('<x-toast>{{ $message }}</x-toast>', ['message' => $message]),
                'coach' => $this->lorie->messageForDay($request->user(), $day),
            ]);
        }

        return redirect()
            ->route('dashboard', ['date' => $day->date])
            ->with('success', $message);
    }
}
