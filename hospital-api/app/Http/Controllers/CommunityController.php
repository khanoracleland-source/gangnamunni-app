<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CommunityController extends Controller
{
    /**
     * Display a listing of community posts
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 20);
            $category = $request->input('category');
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            $query = DB::table('community_posts')
                ->select(
                    'community_posts.*',
                    DB::raw('(SELECT COUNT(*) FROM community_comments WHERE post_id = community_posts.id) as comment_count')
                );

            if ($category) {
                $query->where('category', $category);
            }

            $posts = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

            return $this->sendResponse($posts, '게시글 목록을 성공적으로 불러왔습니다.');
        } catch (\Exception $e) {
            return $this->sendError('게시글 목록 조회 실패', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created post
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->sendError('입력 데이터 검증 실패', $validator->errors(), 422);
        }

        try {
            $postId = DB::table('community_posts')->insertGetId([
                'user_id' => $request->user_id,
                'title' => $request->title,
                'content' => $request->content,
                'category' => $request->category ?? 'general',
                'views' => 0,
                'likes' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $post = DB::table('community_posts')->find($postId);

            return $this->sendResponse($post, '게시글이 성공적으로 작성되었습니다.');
        } catch (\Exception $e) {
            return $this->sendError('게시글 작성 실패', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified post
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            // Increment views
            DB::table('community_posts')->where('id', $id)->increment('views');

            $post = DB::table('community_posts')
                ->select(
                    'community_posts.*',
                    DB::raw('(SELECT COUNT(*) FROM community_comments WHERE post_id = community_posts.id) as comment_count')
                )
                ->where('community_posts.id', $id)
                ->first();

            if (!$post) {
                return $this->sendError('게시글을 찾을 수 없습니다.', [], 404);
            }

            return $this->sendResponse($post, '게시글을 성공적으로 불러왔습니다.');
        } catch (\Exception $e) {
            return $this->sendError('게시글 조회 실패', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified post
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->sendError('입력 데이터 검증 실패', $validator->errors(), 422);
        }

        try {
            $post = DB::table('community_posts')->find($id);

            if (!$post) {
                return $this->sendError('게시글을 찾을 수 없습니다.', [], 404);
            }

            // Check if user owns the post
            if ($post->user_id != $request->user_id) {
                return $this->sendError('게시글 수정 권한이 없습니다.', [], 403);
            }

            DB::table('community_posts')->where('id', $id)->update([
                'title' => $request->title,
                'content' => $request->content,
                'category' => $request->category ?? $post->category,
                'updated_at' => now(),
            ]);

            $updatedPost = DB::table('community_posts')->find($id);

            return $this->sendResponse($updatedPost, '게시글이 성공적으로 수정되었습니다.');
        } catch (\Exception $e) {
            return $this->sendError('게시글 수정 실패', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified post
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            $post = DB::table('community_posts')->find($id);

            if (!$post) {
                return $this->sendError('게시글을 찾을 수 없습니다.', [], 404);
            }

            // Check if user owns the post
            if ($post->user_id != $request->input('user_id')) {
                return $this->sendError('게시글 삭제 권한이 없습니다.', [], 403);
            }

            DB::table('community_posts')->where('id', $id)->delete();

            return $this->sendResponse([], '게시글이 성공적으로 삭제되었습니다.');
        } catch (\Exception $e) {
            return $this->sendError('게시글 삭제 실패', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get comments for a specific post
     *
     * @param  int  $postId
     * @return \Illuminate\Http\Response
     */
    public function getComments($postId)
    {
        try {
            $comments = DB::table('community_comments')
                ->where('post_id', $postId)
                ->orderBy('created_at', 'asc')
                ->get();

            return $this->sendResponse($comments, '댓글 목록을 성공적으로 불러왔습니다.');
        } catch (\Exception $e) {
            return $this->sendError('댓글 목록 조회 실패', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a comment for a post
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $postId
     * @return \Illuminate\Http\Response
     */
    public function storeComment(Request $request, $postId)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('입력 데이터 검증 실패', $validator->errors(), 422);
        }

        try {
            // Check if post exists
            $post = DB::table('community_posts')->find($postId);
            if (!$post) {
                return $this->sendError('게시글을 찾을 수 없습니다.', [], 404);
            }

            $commentId = DB::table('community_comments')->insertGetId([
                'post_id' => $postId,
                'user_id' => $request->user_id,
                'content' => $request->content,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $comment = DB::table('community_comments')->find($commentId);

            return $this->sendResponse($comment, '댓글이 성공적으로 작성되었습니다.');
        } catch (\Exception $e) {
            return $this->sendError('댓글 작성 실패', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a comment
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $postId
     * @param  int  $commentId
     * @return \Illuminate\Http\Response
     */
    public function destroyComment(Request $request, $postId, $commentId)
    {
        try {
            $comment = DB::table('community_comments')->find($commentId);

            if (!$comment) {
                return $this->sendError('댓글을 찾을 수 없습니다.', [], 404);
            }

            // Check if user owns the comment
            if ($comment->user_id != $request->input('user_id')) {
                return $this->sendError('댓글 삭제 권한이 없습니다.', [], 403);
            }

            DB::table('community_comments')->where('id', $commentId)->delete();

            return $this->sendResponse([], '댓글이 성공적으로 삭제되었습니다.');
        } catch (\Exception $e) {
            return $this->sendError('댓글 삭제 실패', ['error' => $e->getMessage()], 500);
        }
    }
}
