import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'review_editor_webview.dart';

/// 후기 목록 화면 (네이티브 Flutter)
/// API로 후기 데이터를 가져와 표시
class ReviewListScreen extends StatefulWidget {
  final String baseUrl;
  final int hospitalId;
  final String userId;

  const ReviewListScreen({
    Key? key,
    required this.baseUrl,
    required this.hospitalId,
    required this.userId,
  }) : super(key: key);

  @override
  State<ReviewListScreen> createState() => _ReviewListScreenState();
}

class _ReviewListScreenState extends State<ReviewListScreen> {
  List<dynamic> _reviews = [];
  bool _isLoading = true;
  bool _hasMore = true;
  int _page = 1;
  String _sort = 'latest';
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _loadReviews();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      if (!_isLoading && _hasMore) {
        _loadMore();
      }
    }
  }

  Future<void> _loadReviews() async {
    setState(() {
      _isLoading = true;
      _page = 1;
    });

    try {
      final uri = Uri.parse(
        '${widget.baseUrl}/api/list_reviews.php'
        '?hospital_id=${widget.hospitalId}'
        '&page=$_page&limit=20&sort=$_sort',
      );
      final response = await http.get(uri);
      final data = json.decode(response.body);

      if (data['success'] == true) {
        setState(() {
          _reviews = data['reviews'] ?? [];
          _hasMore = data['has_more'] ?? false;
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _loadMore() async {
    _page++;
    setState(() => _isLoading = true);

    try {
      final uri = Uri.parse(
        '${widget.baseUrl}/api/list_reviews.php'
        '?hospital_id=${widget.hospitalId}'
        '&page=$_page&limit=20&sort=$_sort',
      );
      final response = await http.get(uri);
      final data = json.decode(response.body);

      if (data['success'] == true) {
        setState(() {
          _reviews.addAll(data['reviews'] ?? []);
          _hasMore = data['has_more'] ?? false;
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _toggleLike(int reviewId, int index) async {
    try {
      final uri = Uri.parse('${widget.baseUrl}/api/like_review.php');
      final response = await http.post(uri,
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'review_id': reviewId,
          'user_id': widget.userId,
        }),
      );
      final data = json.decode(response.body);

      if (data['success'] == true) {
        setState(() {
          _reviews[index]['like_count'] = data['like_count'];
          _reviews[index]['liked'] = data['liked'];
        });
      }
    } catch (e) {
      // ignore
    }
  }

  void _openEditor({int? reviewId}) async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ReviewEditorWebView(
          baseUrl: widget.baseUrl,
          hospitalId: widget.hospitalId,
          userId: widget.userId,
          reviewId: reviewId,
        ),
      ),
    );

    if (result != null && result['success'] == true) {
      _loadReviews(); // 새로고침
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('후기'),
        backgroundColor: Colors.white,
        foregroundColor: Colors.black87,
        elevation: 0.5,
        actions: [
          PopupMenuButton<String>(
            onSelected: (value) {
              _sort = value;
              _loadReviews();
            },
            itemBuilder: (_) => [
              const PopupMenuItem(value: 'latest', child: Text('최신순')),
              const PopupMenuItem(value: 'rating', child: Text('평점순')),
              const PopupMenuItem(value: 'popular', child: Text('인기순')),
            ],
          ),
        ],
      ),
      body: _isLoading && _reviews.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadReviews,
              child: ListView.separated(
                controller: _scrollController,
                padding: const EdgeInsets.all(16),
                itemCount: _reviews.length + (_hasMore ? 1 : 0),
                separatorBuilder: (_, __) => const Divider(height: 24),
                itemBuilder: (context, index) {
                  if (index >= _reviews.length) {
                    return const Center(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: CircularProgressIndicator(),
                      ),
                    );
                  }
                  return _buildReviewCard(_reviews[index], index);
                },
              ),
            ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openEditor(),
        backgroundColor: const Color(0xFF7C3AED),
        icon: const Icon(Icons.edit),
        label: const Text('후기 작성'),
      ),
    );
  }

  Widget _buildReviewCard(Map<String, dynamic> review, int index) {
    final rating = review['rating'] ?? 0;
    final images = (review['images'] as List?) ?? [];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // 헤더
        Row(
          children: [
            CircleAvatar(
              radius: 18,
              backgroundColor: Colors.grey[200],
              child: const Icon(Icons.person, color: Colors.grey, size: 20),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    review['user_name'] ?? '익명',
                    style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                  ),
                  Text(
                    review['time_ago'] ?? '',
                    style: TextStyle(color: Colors.grey[500], fontSize: 12),
                  ),
                ],
              ),
            ),
            if (review['is_verified'] == true)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.green[50],
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text('인증', style: TextStyle(color: Colors.green[700], fontSize: 11)),
              ),
          ],
        ),

        const SizedBox(height: 10),

        // 별점
        Row(
          children: List.generate(5, (i) => Icon(
            i < rating ? Icons.star : Icons.star_border,
            color: const Color(0xFFFBBF24),
            size: 18,
          )),
        ),

        const SizedBox(height: 8),

        // 시술 태그
        if (review['treatments'] != null && review['treatments'].toString().isNotEmpty)
          Wrap(
            spacing: 6,
            runSpacing: 4,
            children: review['treatments'].toString().split(',').map<Widget>((t) {
              return Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFFF0F4FF),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(t.trim(), style: const TextStyle(fontSize: 12, color: Color(0xFF4A90E2))),
              );
            }).toList(),
          ),

        if (review['treatments'] != null && review['treatments'].toString().isNotEmpty)
          const SizedBox(height: 8),

        // 제목
        if (review['title'] != null && review['title'].toString().isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: Text(
              review['title'],
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
            ),
          ),

        // 내용
        Text(
          review['content'] ?? '',
          maxLines: 4,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontSize: 14, height: 1.6),
        ),

        // 이미지
        if (images.isNotEmpty) ...[
          const SizedBox(height: 10),
          SizedBox(
            height: 80,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: images.length,
              separatorBuilder: (_, __) => const SizedBox(width: 6),
              itemBuilder: (_, i) => ClipRRect(
                borderRadius: BorderRadius.circular(6),
                child: Image.network(
                  images[i]['thumbnail_url'] ?? images[i]['image_url'] ?? '',
                  width: 80,
                  height: 80,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => Container(
                    width: 80, height: 80,
                    color: Colors.grey[200],
                    child: const Icon(Icons.image_not_supported, color: Colors.grey),
                  ),
                ),
              ),
            ),
          ),
        ],

        // 좋아요/댓글
        const SizedBox(height: 12),
        Row(
          children: [
            GestureDetector(
              onTap: () => _toggleLike(review['id'], index),
              child: Row(
                children: [
                  Icon(
                    review['liked'] == true ? Icons.favorite : Icons.favorite_border,
                    size: 18,
                    color: review['liked'] == true ? Colors.red : Colors.grey,
                  ),
                  const SizedBox(width: 4),
                  Text('${review['like_count'] ?? 0}', style: TextStyle(color: Colors.grey[600], fontSize: 13)),
                ],
              ),
            ),
            const SizedBox(width: 16),
            Row(
              children: [
                Icon(Icons.chat_bubble_outline, size: 16, color: Colors.grey[500]),
                const SizedBox(width: 4),
                Text('${review['comment_count'] ?? 0}', style: TextStyle(color: Colors.grey[600], fontSize: 13)),
              ],
            ),
          ],
        ),
      ],
    );
  }
}
