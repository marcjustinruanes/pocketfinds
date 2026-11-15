import 'package:flutter/material.dart';

import 'product_image.dart';

class ProductGallery extends StatefulWidget {
  final List<String> images;
  const ProductGallery({super.key, required this.images});
  @override
  State<ProductGallery> createState() => _ProductGalleryState();
}

class _ProductGalleryState extends State<ProductGallery> {
  int _index = 0;
  @override
  Widget build(BuildContext context) => AspectRatio(
    aspectRatio: 1,
    child: Stack(
      children: [
        if (widget.images.isEmpty)
          const ProductImage(imageUrl: null)
        else
          PageView(
            onPageChanged: (i) => setState(() => _index = i),
            children: [
              for (final url in widget.images)
                GestureDetector(
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => Scaffold(
                        appBar: AppBar(title: const Text('Product photo')),
                        body: Center(
                          child: InteractiveViewer(
                            minScale: 1,
                            maxScale: 5,
                            child: ProductImage(
                              imageUrl: url,
                              fit: BoxFit.contain,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                  child: ProductImage(imageUrl: url, fit: BoxFit.contain),
                ),
            ],
          ),
        if (widget.images.length > 1)
          Positioned(
            bottom: 12,
            right: 16,
            child: Chip(label: Text('${_index + 1} / ${widget.images.length}')),
          ),
      ],
    ),
  );
}
