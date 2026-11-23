import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

/// A simple full-screen, pinch-to-zoom viewer for a tapped chat photo —
/// pushed from `MessageBubble`, closed by tapping anywhere or the back
/// button.
class FullImageViewer extends StatelessWidget {
  final String imageUrl;

  const FullImageViewer({super.key, required this.imageUrl});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: GestureDetector(
        onTap: () => Navigator.of(context).pop(),
        child: Center(
          child: InteractiveViewer(
            minScale: 1,
            maxScale: 4,
            child: CachedNetworkImage(
              imageUrl: imageUrl,
              fit: BoxFit.contain,
              placeholder: (context, url) =>
                  const CircularProgressIndicator(color: Colors.white),
              errorWidget: (context, url, error) => const Icon(
                Icons.broken_image_outlined,
                color: Colors.white54,
                size: 48,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
