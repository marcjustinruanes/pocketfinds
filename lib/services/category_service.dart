import '../models/category.dart';
import 'backend_service.dart';

/// Reads categories straight from the existing `categories` table —
/// no hardcoded list.
class CategoryService {
  Future<List<Category>> fetchAll() async {
    final rows = await BackendService.client
        .from('categories')
        .select('id, name')
        .order('name');

    return rows.map((row) => Category.fromMap(row)).toList();
  }
}
