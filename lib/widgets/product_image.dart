import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// A product image, or a consistent placeholder when there isn't a usable
/// one yet (see the note on `Product.imageUrl`) — matches the website's
/// `.product-img` treatment exactly: a soft diagonal pink-to-grey gradient
/// behind a centered icon, not a flat color block.
class ProductImage extends StatelessWidget {
  final String? imageUrl;
  final BoxFit fit;
  final double placeholderIconSize;

  const ProductImage({
    super.key,
    required this.imageUrl,
    this.fit = BoxFit.cover,
    this.placeholderIconSize = 36,
  });

  @override
  Widget build(BuildContext context) {
    if (imageUrl == null) return _placeholder();
    return CachedNetworkImage(
      imageUrl: imageUrl!,
      fit: fit,
      placeholder: (context, url) => _gradientBox(),
      errorWidget: (context, url, error) => _placeholder(),
    );
  }

  Widget _placeholder() {
    return _gradientBox(
      child: Center(
        child: Icon(
          Icons.image_outlined,
          size: placeholderIconSize,
          color: AppColors.pink.withValues(alpha: 0.55),
        ),
      ),
    );
  }

  Widget _gradientBox({Widget? child}) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.pinkSoft, Color(0xFFF4F4F5)],
        ),
      ),
      child: child,
    );
  }
}
