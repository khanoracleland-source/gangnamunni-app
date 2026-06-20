<?php

namespace App\Http\Controllers;

use App\Models\GangnamunniHospital;
use App\Models\HospitalReview;
use App\Models\ReviewReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HospitalReviewController extends AppBaseController
{
    public function index(Request $request): View
    {
        return view('hospital_reviews.index');
    }

    public function getData(Request $request): JsonResponse
    {
        $tenantId = tenant('id');
        $gnHospital = GangnamunniHospital::findByTenantId($tenantId);

        if (!$gnHospital) {
            return response()->json([
                'data' => [],
                'stats' => ['total' => 0, 'active' => 0, 'inactive' => 0, 'reported' => 0, 'avg_rating' => 0],
                'total' => 0, 'current_page' => 1, 'last_page' => 1, 'per_page' => 20,
            ]);
        }

        $hospitalId = $gnHospital->id;
        $query = HospitalReview::where('hospital_id', $hospitalId);

        $status = $request->get('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }

        if ($request->filled('search')) {
            $s = '%' . $request->get('search') . '%';
            $query->where(function ($q) use ($s) {
                $q->where('user_name', 'like', $s)
                  ->orWhere('content', 'like', $s)
                  ->orWhere('title', 'like', $s);
            });
        }

        $stats = [
            'total' => HospitalReview::where('hospital_id', $hospitalId)->count(),
            'active' => HospitalReview::where('hospital_id', $hospitalId)->where('is_active', 1)->count(),
            'inactive' => HospitalReview::where('hospital_id', $hospitalId)->where('is_active', 0)->count(),
            'reported' => (int) DB::table('review_reports')
                ->join('hospital_reviews', 'review_reports.review_id', '=', 'hospital_reviews.id')
                ->where('hospital_reviews.hospital_id', $hospitalId)
                ->where('review_reports.status', 'pending')
                ->distinct('review_reports.review_id')
                ->count('review_reports.review_id'),
            'avg_rating' => round((float) (HospitalReview::where('hospital_id', $hospitalId)->where('is_active', 1)->avg('rating') ?? 0), 1),
        ];

        $perPage = min((int) $request->get('per_page', 20), 100);
        $reviews = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $data = $reviews->map(function ($r) {
            return [
                'id' => $r->id,
                'user_name' => $r->user_name,
                'title' => $r->title,
                'content' => $r->content,
                'rating' => $r->rating,
                'is_active' => $r->is_active,
                'is_verified' => $r->is_verified,
                'treatments' => $r->treatments,
                'like_count' => $r->like_count,
                'comment_count' => $r->comment_count,
                'report_count' => (int) DB::table('review_reports')->where('review_id', $r->id)->where('status', 'pending')->count(),
                'created_at' => optional($r->created_at)->format('Y-m-d H:i'),
                'updated_at' => optional($r->updated_at)->format('Y-m-d H:i'),
            ];
        });

        return response()->json([
            'data' => $data,
            'stats' => $stats,
            'total' => $reviews->total(),
            'current_page' => $reviews->currentPage(),
            'last_page' => $reviews->lastPage(),
            'per_page' => $reviews->perPage(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $review = HospitalReview::forCurrentTenant()->findOrFail($id);
        $review->load(['reviewImages', 'reviewComments']);
        return $this->sendResponse($review->toArray(), 'Review retrieved.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $review = HospitalReview::forCurrentTenant()->findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:200',
            'content' => 'required|string|min:1',
            'rating' => 'required|integer|min:1|max:5',
            'user_name' => 'required|string|max:50',
            'is_active' => 'required|boolean',
        ]);

        $review->update($validated);
        $this->updateHospitalStats($review->hospital_id);

        return $this->sendSuccess('\xed\x9b\x84\xea\xb8\xb0\xea\xb0\x80 \xec\x88\x98\xec\xa0\x95\xeb\x90\x98\xec\x97\x88\xec\x8a\xb5\xeb\x8b\x88\xeb\x8b\xa4.');
    }

    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $review = HospitalReview::forCurrentTenant()->findOrFail($id);

        $action = $request->get('action', 'toggle');
        if ($action === 'approve') {
            $review->update(['is_active' => 1]);
            $msg = '\xed\x9b\x84\xea\xb8\xb0 \xec\x8a\xb9\xec\x9d\xb8';
        } elseif ($action === 'reject') {
            $review->update(['is_active' => 0]);
            $msg = '\xed\x9b\x84\xea\xb8\xb0 \xea\xb1\xb0\xeb\xb6\x80';
        } else {
            $review->update(['is_active' => !$review->is_active]);
            $msg = '\xec\x83\x81\xed\x83\x9c \xeb\xb3\x80\xea\xb2\xbd';
        }

        $this->updateHospitalStats($review->hospital_id);

        ReviewReport::where('review_id', $id)
            ->where('status', 'pending')
            ->update(['status' => 'reviewed', 'processed_at' => now()]);

        return $this->sendSuccess($msg);
    }

    public function bulkAction(Request $request): JsonResponse
    {
        $ids = $request->get('ids', []);
        $action = $request->get('action');

        if (empty($ids) || !in_array($action, ['approve', 'reject', 'delete'])) {
            return $this->sendError('Invalid request.');
        }

        $reviews = HospitalReview::forCurrentTenant()->whereIn('id', $ids)->get();
        if ($reviews->count() !== count($ids)) {
            return $this->sendError('Access denied for some reviews.');
        }

        $hospitalIds = $reviews->pluck('hospital_id')->unique();

        if ($action === 'approve') {
            HospitalReview::whereIn('id', $ids)->update(['is_active' => 1]);
            $msg = count($ids) . ' reviews approved.';
        } elseif ($action === 'reject') {
            HospitalReview::whereIn('id', $ids)->update(['is_active' => 0]);
            $msg = count($ids) . ' reviews rejected.';
        } else {
            DB::table('review_images')->whereIn('review_id', $ids)->delete();
            DB::table('review_comments')->whereIn('review_id', $ids)->delete();
            DB::table('review_reports')->whereIn('review_id', $ids)->delete();
            DB::table('review_likes')->whereIn('review_id', $ids)->delete();
            HospitalReview::whereIn('id', $ids)->delete();
            $msg = count($ids) . ' reviews deleted.';
        }

        foreach ($hospitalIds as $hid) {
            $this->updateHospitalStats($hid);
        }

        return $this->sendSuccess($msg);
    }

    private function updateHospitalStats(int $hospitalId): void
    {
        DB::table('gangnamunni_hospitals')
            ->where('id', $hospitalId)
            ->update([
                'review_count' => DB::table('hospital_reviews')->where('hospital_id', $hospitalId)->where('is_active', 1)->count(),
                'rating' => DB::table('hospital_reviews')->where('hospital_id', $hospitalId)->where('is_active', 1)->avg('rating') ?? 0,
            ]);
    }
}
