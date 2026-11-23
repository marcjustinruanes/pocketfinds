import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../models/category.dart';
import '../../services/category_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/category_icons.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/pressable.dart';
import '../../widgets/skeleton.dart';
import 'category_products_screen.dart';

/// The full category grid — pushed from Home's "Browse Categories" section
/// via its "See All" link (previously a standalone bottom-nav tab; the
/// Buyer nav no longer has a Categories tab, so this is reached from Home
/// instead, matching the original website's "Browse by Category card + View
/// all link" pattern). Every tile still leads to the same
/// `CategoryProductsScreen`. `user` is nullable — also reached from Guest
/// Home's Categories "See All".
class AllCategoriesScreen extends StatefulWidget {
  final AppUser? user;

  const AllCategoriesScreen({super.key, required this.user});

  @override
  State<AllCategoriesScreen> createState() => _AllCategoriesScreenState();
}

class _AllCategoriesScreenState extends State<AllCategoriesScreen> {
  final _categoryService = CategoryService();
  late Future<List<Category>> _future;

  @override
  void initState() {
    super.initState();
    _future = _categoryService.fetchAll();
  }

  Future<void> _refresh() async {
    setState(() => _future = _categoryService.fetchAll());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text('Categories', style: AppTypography.pageTitle)),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<List<Category>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return GridView.builder(
                padding: const EdgeInsets.all(16),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  childAspectRatio: 1.6,
                ),
                itemCount: 6,
                itemBuilder: (context, index) => const SkeletonListTile(),
              );
            }
            if (snapshot.hasError) {
              return ListView(
                children: [
                  ErrorState(
                    message: 'Could not load categories.',
                    error: friendlyError(snapshot.error!),
                    onRetry: _refresh,
                  ),
                ],
              );
            }
            final categories = snapshot.data ?? [];
            if (categories.isEmpty) {
              return ListView(
                children: const [
                  EmptyState(
                    icon: Icons.category_outlined,
                    title: 'No categories yet',
                  ),
                ],
              );
            }
            return GridView.builder(
              padding: const EdgeInsets.all(16),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: 1.6,
              ),
              itemCount: categories.length,
              itemBuilder: (context, index) {
                final category = categories[index];
                return _CategoryTile(user: widget.user, category: category);
              },
            );
          },
        ),
      ),
    );
  }
}

class _CategoryTile extends StatelessWidget {
  final AppUser? user;
  final Category category;

  const _CategoryTile({required this.user, required this.category});

  @override
  Widget build(BuildContext context) {
    return Pressable(
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) =>
              CategoryProductsScreen(user: user, category: category),
        ),
      ),
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.white,
          border: Border.all(color: AppColors.border),
          borderRadius: BorderRadius.circular(AppRadius.card),
          boxShadow: AppShadows.sm,
        ),
        padding: const EdgeInsets.symmetric(vertical: 18, horizontal: 10),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                color: AppColors.pinkSoft,
                shape: BoxShape.circle,
              ),
              child: Icon(
                iconForCategory(category.name),
                color: AppColors.pink,
              ),
            ),
            const SizedBox(height: 10),
            Text(
              category.name,
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: AppTypography.productTitle.copyWith(fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}
