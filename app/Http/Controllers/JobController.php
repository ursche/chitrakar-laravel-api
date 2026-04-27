<?php

namespace App\Http\Controllers;

use App\Models\AiJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobController extends Controller
{
    private const SERVICE_CREDITS = [
        'virtual-mannequin'  => 4,
        'product-staging'    => 3,
        'promotional-banner' => 2,
    ];

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $query = AiJob::where('user_id', $request->user()->id)->latest();

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->query('service_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $paginator = $query->paginate($perPage);
        $data = collect($paginator->items())->map(fn (AiJob $j) => $this->jobShape($j))->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_type'    => 'required|string|in:virtual-mannequin,product-staging,promotional-banner',
            'input_image_url' => 'required|url',
            'prompt_payload'  => 'required|array',
        ]);

        $creditCost   = self::SERVICE_CREDITS[$validated['service_type']];
        $job          = null;
        $insufficient = false;

        DB::transaction(function () use ($request, $validated, $creditCost, &$job, &$insufficient) {
            $user = User::lockForUpdate()->find($request->user()->id);
            if ($user->credits < $creditCost) {
                $insufficient = true;
                return;
            }
            $user->decrement('credits', $creditCost);
            $job = AiJob::create([
                'user_id'         => $user->id,
                'service_type'    => $validated['service_type'],
                'input_image_url' => $validated['input_image_url'],
                'prompt_payload'  => $validated['prompt_payload'],
                'output_urls'     => [],
                'status'          => 'processing',
                'credits_used'    => $creditCost,
            ]);
        });

        if ($insufficient) {
            return response()->json(['message' => 'Insufficient credits.'], 402);
        }

        return response()->json($this->jobShape($job), 201);
    }

    public function show(Request $request, string $job): JsonResponse
    {
        $aiJob = AiJob::findOrFail($job);  // 404 if not found

        if ($aiJob->user_id !== $request->user()->id) {
            abort(403, 'Forbidden.');      // 403 if wrong owner
        }

        return response()->json($this->jobShape($aiJob));
    }

    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $stats = AiJob::where('user_id', $userId)
            ->selectRaw("
                COUNT(*) as total_jobs,
                SUM(CASE WHEN status = 'complete'   THEN 1 ELSE 0 END) as completed_jobs,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_jobs,
                SUM(CASE WHEN status = 'failed'     THEN 1 ELSE 0 END) as failed_jobs,
                SUM(credits_used) as total_credits_used
            ")
            ->first();

        return response()->json([
            'total_jobs'         => (int) ($stats->total_jobs ?? 0),
            'completed_jobs'     => (int) ($stats->completed_jobs ?? 0),
            'processing_jobs'    => (int) ($stats->processing_jobs ?? 0),
            'failed_jobs'        => (int) ($stats->failed_jobs ?? 0),
            'total_credits_used' => (int) ($stats->total_credits_used ?? 0),
        ]);
    }

    private function jobShape(AiJob $job): array
    {
        return [
            'id'              => $job->id,
            'user_id'         => $job->user_id,
            'service_type'    => $job->service_type,
            'input_image_url' => $job->input_image_url,
            'prompt_payload'  => $job->prompt_payload,
            'status'          => $job->status,
            'output_urls'     => $job->output_urls ?? [],
            'credits_used'    => $job->credits_used,
            'created_at'      => $job->created_at->toIso8601String(),
            'completed_at'    => $job->completed_at?->toIso8601String(),
        ];
    }
}
