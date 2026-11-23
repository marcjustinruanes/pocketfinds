import 'package:flutter/material.dart';

/// Picks a representative icon for a category by matching keywords in its
/// name — the real categories are plain text with no icon field of their
/// own, so this is a small presentational nicety, not invented data.
IconData iconForCategory(String name) {
  final lower = name.toLowerCase();
  if (lower.contains('pet')) return Icons.pets;
  if (lower.contains('electronic') || lower.contains('gadget')) {
    return Icons.devices_other;
  }
  if (lower.contains("women")) return Icons.checkroom;
  if (lower.contains('men')) return Icons.checkroom_outlined;
  if (lower.contains('kid') || lower.contains('baby')) {
    return Icons.child_friendly;
  }
  if (lower.contains('home') || lower.contains('garden')) {
    return Icons.chair_outlined;
  }
  if (lower.contains('sport') || lower.contains('outdoor')) {
    return Icons.sports_basketball_outlined;
  }
  if (lower.contains('health') || lower.contains('beauty')) {
    return Icons.spa_outlined;
  }
  return Icons.category_outlined;
}
