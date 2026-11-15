import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/models/product.dart';

Product fixture({bool requiresSetup = false}) => Product.fromRow(
  row: {
    'id': 'product',
    'name': 'Shirt',
    'seller_id': 1,
    'price': 100,
    'stock': 7,
    'variations': [
      {
        'name': 'Options',
        'type': 'combinations',
        'requires_stock_setup': requiresSetup,
        'axes': [
          {
            'name': 'Color',
            'options': [
              {'value': 'Red'},
              {'value': 'Blue'},
            ],
          },
          {
            'name': 'Size',
            'options': [
              {'value': 'M'},
              {'value': 'L'},
            ],
          },
        ],
        'options': [
          {
            'value': 'Red / M',
            'attributes': {'Color': 'Red', 'Size': 'M'},
            'price': 125,
            'stock': 3,
          },
          {
            'value': 'Blue / L',
            'attributes': {'Color': 'Blue', 'Size': 'L'},
            'price': 150,
            'stock': 0,
          },
        ],
      },
    ],
  },
);

void main() {
  test('color and size stay separate but resolve to one website SKU', () {
    final p = fixture();
    expect(p.variations.map((v) => v.name), ['Color', 'Size']);
    expect(p.selectedOption({'Color': 'Red'}), isNull);
    expect(p.selectedOption({'Color': 'Red', 'Size': 'L'}), isNull);
    final selected = p.selectedOption({'Color': 'Red', 'Size': 'M'})!;
    expect(selected.group, 'Options');
    expect(selected.value, 'Red / M');
    expect(selected.stock, 3);
    expect(selected.price, 125);
    expect(p.selectedOption({'Color': 'Blue', 'Size': 'L'})!.stock, 0);
  });
  test('legacy alternative SKU groups retain exclusive choices', () {
    final p = Product.fromRow(
      row: {
        'id': 'legacy',
        'name': 'Serum',
        'seller_id': 1,
        'price': 100,
        'stock': 3,
        'variations': [
          {
            'name': 'Formula A',
            'options': [
              {'value': '30 ml', 'stock': 1},
            ],
          },
          {
            'name': 'Formula B',
            'options': [
              {'value': '60 ml', 'stock': 2},
            ],
          },
        ],
      },
    );
    expect(p.variations, isEmpty);
    expect(p.purchaseOptions.map((o) => o.group), ['Formula A', 'Formula B']);
    expect(p.purchasable, isTrue);
  });
  test('unconfigured combination stock cannot be purchased', () {
    expect(
      fixture(requiresSetup: true).purchaseOptions.every((o) => o.stock == 0),
      isTrue,
    );
  });
}
