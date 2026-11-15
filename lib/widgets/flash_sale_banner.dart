import 'package:flutter/material.dart';

import '../models/product.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import '../theme/app_typography.dart';
import '../utils/formatters.dart';
import 'primary_button.dart';
import 'product_image.dart';

/// "Flash Sale" — a swipeable banner carousel, one real discounted product
/// per page (real `discount_price`/`discountPercent`, from `Product`).
/// Deliberately no countdown timer or stock-scarcity bar — nothing in the
/// backend backs either (no `deal_ends_at`, no original-stock baseline to
/// compare against); the banner communicates the real discount, not a
/// fabricated deadline. Renders nothing when [products] is empty.
class FlashSaleBanner extends StatefulWidget {
  final List<Product> products;
  final void Function(Product) onTap;

  const FlashSaleBanner({
    super.key,
    required this.products,
    required this.onTap,
  });

  @override
  State<FlashSaleBanner> createState() => _FlashSaleBannerState();
}

class _FlashSaleBannerState extends State<FlashSaleBanner> {
  final _controller = PageController();
  int _page = 0;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.products.isEmpty) return const SizedBox.shrink();
    return Column(
      children: [
        SizedBox(
          height: 200 * MediaQuery.textScalerOf(context).scale(1),
          child: PageView.builder(
            controller: _controller,
            itemCount: widget.products.length,
            onPageChanged: (i) => setState(() => _page = i),
            itemBuilder: (context, index) => _FlashSaleCard(
              product: widget.products[index],
              onTap: () => widget.onTap(widget.products[index]),
            ),
          ),
        ),
        if (widget.products.length > 1) ...[
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(widget.products.length, (i) {
              final active = i == _page;
              return AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                margin: const EdgeInsets.symmetric(horizontal: 3),
                width: active ? 18 : 6,
                height: 6,
                decoration: BoxDecoration(
                  color: active ? AppColors.pink : AppColors.border,
                  borderRadius: BorderRadius.circular(999),
                ),
              );
            }),
          ),
        ],
      ],
    );
  }
}

class _FlashSaleCard extends StatelessWidget {
  final Product product;
  final VoidCallback onTap;

  const _FlashSaleCard({required this.product, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(AppRadius.xl),
      child: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFF4A1836), Color(0xFFA52B6C), Color(0xFFEF7EB5)],
            stops: [0, 0.55, 1],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    'FLASH SALE',
                    style: const TextStyle(
                      color: Colors.white70,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1.2,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Get ${product.discountPercent}% OFF\n${product.name}',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: AppTypography.display.copyWith(
                      color: Colors.white,
                      fontSize: 18,
                      height: 1.2,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Wrap(
                    spacing: 6,
                    children: [
                      Text(
                        formatPeso(product.discountPrice!),
                        style: AppTypography.price.copyWith(
                          color: Colors.white,
                          fontSize: 16,
                        ),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        formatPeso(product.price),
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.6),
                          fontSize: 12,
                          decoration: TextDecoration.lineThrough,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  SecondaryButton(
                    label: 'Shop Now →',
                    onPressed: onTap,
                    fullWidth: false,
                  ),
                ],
              ),
            ),
            const SizedBox(width: AppSpacing.md),
            ClipRRect(
              borderRadius: BorderRadius.circular(AppRadius.card),
              child: SizedBox(
                width: 84,
                height: 110,
                child: ProductImage(imageUrl: product.imageUrl),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
