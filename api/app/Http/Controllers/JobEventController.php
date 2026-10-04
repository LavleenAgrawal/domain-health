<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CheckEvent;
use App\Models\CheckJob;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobEventController extends Controller
{
    public function __invoke(Request $request, CheckJob $checkJob): StreamedResponse
    {
        $after = max(0, (int) $request->header('Last-Event-ID', $request->integer('after', 0)));
        return response()->stream(function () use ($checkJob, $after): void { @set_time_limit(35); echo "retry: 2000\n\n"; $cursor = $after; $until = microtime(true) + 25; while (microtime(true) < $until && !connection_aborted()) { $events = CheckEvent::where('check_job_id', $checkJob->id)->where('id', '>', $cursor)->orderBy('id')->limit(100)->get(); foreach ($events as $event) { echo "id: {$event->id}\nevent: {$event->type}\ndata: ".json_encode($event->payload, JSON_UNESCAPED_SLASHES)."\n\n"; $cursor = $event->id; } if ($events->isEmpty()) echo ": heartbeat\n\n"; @ob_flush(); flush(); usleep(1000000); } }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache, no-transform', 'X-Accel-Buffering' => 'no', 'Connection' => 'keep-alive']);
    }
}

