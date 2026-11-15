import 'package:pocketfinds/widgets/auth_text_field.dart';

import 'dart:convert';
import 'dart:typed_data';

import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/screens/auth/account_type_screen.dart';
import 'package:pocketfinds/screens/auth/register/buyer_registration_screen.dart';
import 'package:pocketfinds/theme/app_theme.dart';
import 'package:pocketfinds/screens/auth/login_screen.dart';
import 'package:pocketfinds/screens/guest/guest_home_screen.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:pocketfinds/services/psgc_service.dart';
import 'package:pocketfinds/widgets/guest_sign_in_sheet.dart';

class _AddressDirectory extends PsgcService {
  @override
  Future<List<PsgcEntry>> fetchRegions() async => [
    const PsgcEntry(code: 'r', name: 'CALABARZON'),
  ];
  @override
  Future<List<PsgcEntry>> fetchProvinces(String regionCode) async => [
    const PsgcEntry(code: 'p', name: 'Laguna'),
  ];
  @override
  Future<List<PsgcEntry>> fetchCities(String provinceCode) async => [
    const PsgcEntry(code: 'c', name: 'Famy'),
  ];
  @override
  Future<List<PsgcEntry>> fetchCitiesForRegion(String regionCode) async => [];
  @override
  Future<List<PsgcEntry>> fetchBarangays(String cityCode) async => [
    const PsgcEntry(code: 'b', name: 'Salangbato'),
  ];
}

void main() {
  setUpAll(() async {
    SharedPreferences.setMockInitialValues({});
    await BackendService.initialize();
  });
  testWidgets('guest create account offers Buyer and Rider first', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.current,
        home: Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              onPressed: () => showGuestSignInSheet(context),
              child: const Text('Open sign in'),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Open sign in'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Create one'));
    await tester.tap(find.text('Create one'));
    await tester.pumpAndSettle();
    expect(find.byType(AccountTypeScreen), findsOneWidget);
    expect(find.text('Buyer'), findsOneWidget);
    expect(find.text('Rider'), findsOneWidget);
    expect(find.byType(BuyerRegistrationScreen), findsNothing);
  });
  for (final role in ['Buyer', 'Rider']) {
    testWidgets('$role card opens its registration or activation form', (
      tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(theme: AppTheme.current, home: const AccountTypeScreen()),
      );
      await tester.ensureVisible(find.text(role));
      await tester.tap(find.text(role));
      await tester.pumpAndSettle();
      expect(find.byType(BuyerRegistrationScreen), findsOneWidget);
      expect(find.byType(Form), findsOneWidget);
      await tester.tap(find.byTooltip('Back'));
      await tester.pumpAndSettle();
      expect(find.byType(AccountTypeScreen), findsOneWidget);
    });
  }
  for (final useBack in [true]) {
    testWidgets(
      'login returns to guest using ${useBack ? 'Back' : 'Browse as guest'}',
      (tester) async {
        await tester.pumpWidget(
          MaterialApp(theme: AppTheme.current, home: const LoginScreen()),
        );
        expect(find.text('Browse as guest'), findsNothing);
        await tester.tap(
          useBack ? find.byTooltip('Back') : find.text('Browse as guest'),
        );
        await tester.pumpAndSettle();
        expect(find.byType(GuestHomeScreen), findsOneWidget);
        expect(find.byType(LoginScreen), findsNothing);
      },
    );
  }
  testWidgets(
    'buyer starts with mandatory ID and selfie then retains personal details',
    (tester) async {
      Map<String, dynamic>? submitted;
      final client = BackendClient(
        transport: MockClient((request) async {
          if (request.url.path.endsWith('registration-terms')) {
            return http.Response(
              '{"title":"Buyer Terms","content":"Published buyer terms."}',
              200,
            );
          }
          if (request.url.path.endsWith('registration-username')) {
            return http.Response('{"available":true}', 200);
          }
          if (request.url.path.endsWith('registration-send-code')) {
            return http.Response('{"sent":true}', 200);
          }
          if (request.url.path.endsWith('registration-verify-code')) {
            return http.Response('{"email_token":"verified-email-proof"}', 200);
          }
          if (request.url.path.endsWith('register-buyer')) {
            submitted = Map<String, dynamic>.from(
              jsonDecode(request.body) as Map,
            );
            return http.Response('{"submitted":true,"status":"pending"}', 200);
          }
          return http.Response(
            jsonEncode([
              {'id': 1, 'name': 'National ID'},
            ]),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.current,
          home: BuyerRegistrationScreen(
            client: client,
            addressService: _AddressDirectory(),
            documentPicker: (kind) async => XFile.fromData(
              Uint8List.fromList([1, 2, 3]),
              name: '$kind.png',
              mimeType: 'image/png',
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      Future<void> tap(String text) async {
        await tester.ensureVisible(find.text(text));
        await tester.pumpAndSettle();
        await tester.tap(find.text(text));
        await tester.pumpAndSettle();
      }

      expect(find.text('Step 1 of 5 · ID & Selfie'), findsOneWidget);
      await tap('Continue');
      expect(find.text('Step 1 of 5 · ID & Selfie'), findsOneWidget);
      await tap('ID type');
      await tester.tap(find.text('National ID').last);
      await tester.pumpAndSettle();
      final lockedSelfie = tester.widget<IgnorePointer>(
        find
            .ancestor(
              of: find.text('Identity selfie'),
              matching: find.byType(IgnorePointer),
            )
            .first,
      );
      expect(lockedSelfie.ignoring, isTrue);
      await tap('Government-issued ID');
      await tap('Identity selfie');
      await tap('Continue');
      expect(find.text('Step 2 of 5 · Personal'), findsOneWidget);
      await tester.enterText(
        find.byKey(const ValueKey('buyer-first')),
        'Chlouie',
      );
      await tester.enterText(find.byKey(const ValueKey('buyer-last')), 'Cabot');
      await tap('I have no middle name');
      await tap('Sex');
      await tester.tap(find.text('Female').last);
      await tester.pumpAndSettle();
      await tap('Continue');
      expect(
        find.text('Select your date of birth. You must be at least 16.'),
        findsOneWidget,
      );
      await tap('Date of birth (16 or older)');
      await tap('OK');
      await tap('Continue');
      expect(find.text('Step 3 of 5 · Contact'), findsOneWidget);
      await tester.enterText(
        find.byKey(const ValueKey('buyer-email')),
        'buyer@gmail.com',
      );
      await tester.enterText(
        find.byKey(const ValueKey('buyer-phone')),
        '09123456789',
      );
      await tap('Continue');
      expect(find.text('Step 3 of 5 · Contact'), findsOneWidget);
      await tester.tap(find.byTooltip('Back'));
      await tester.pumpAndSettle();
      expect(find.text('Chlouie'), findsOneWidget);
      await tester.tap(find.byTooltip('Back'));
      await tester.pumpAndSettle();
      expect(find.text('Step 1 of 5 · ID & Selfie'), findsOneWidget);
      await tester.drag(find.byType(Scrollable).first, const Offset(0, -400));
      await tester.pumpAndSettle();
      expect(find.text('Tap to replace'), findsNWidgets(2));
      expect(find.text('Browse as guest'), findsNothing);
      await tap('Continue');
      await tap('Continue');
      await tester.enterText(
        find.byKey(const ValueKey('buyer-phone')),
        '09abc123456789',
      );
      expect(
        tester
            .widget<AuthTextField>(find.byKey(const ValueKey('buyer-phone')))
            .controller
            .text,
        '09123456789',
      );
      await tap('Send verification code');
      final otp = find.widgetWithText(AuthTextField, '6-digit email code');
      await tester.enterText(otp, '123456');
      await tap('Verify email');
      expect(find.text('Email verified'), findsOneWidget);
      await tap('Continue');
      expect(find.text('Step 4 of 5 · Address'), findsOneWidget);
      Future<void> select(String label, String choice) async {
        await tap(label);
        await tester.tap(find.text(choice).last);
        await tester.pumpAndSettle();
      }

      await select('Region', 'CALABARZON');
      await select('Province', 'Laguna');
      await select('City / municipality', 'Famy');
      await select('Barangay', 'Salangbato');
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Postal code'),
        '4020',
      );
      await tester.enterText(
        find.byKey(const ValueKey('registration-street')),
        'Test Street',
      );
      await tap('Continue');
      expect(find.text('Step 5 of 5 · Account'), findsOneWidget);
      await tester.enterText(
        find.widgetWithText(AuthTextField, 'Username'),
        'testbuyer123',
      );
      await tester.pump(const Duration(milliseconds: 600));
      await tester.pumpAndSettle();
      expect(find.text('Username is available.'), findsOneWidget);
      expect(find.text('Your details'), findsNothing);
      await tester.enterText(
        find.byKey(const ValueKey('buyer-password')),
        'TestPass123!',
      );
      await tester.enterText(
        find.byKey(const ValueKey('buyer-confirmation')),
        'TestPass123!',
      );
      await tap('Read buyer terms');
      await tap('I have read the terms');
      await tester.ensureVisible(find.byType(CheckboxListTile));
      await tester.tap(find.byType(CheckboxListTile));
      await tap('Submit application');
      expect(submitted?['flow'], 'website');
      expect(submitted?['email_token'], 'verified-email-proof');
      expect(submitted?['postal_code'], '4020');
      expect(submitted?['username'], 'testbuyer123');
      expect(
        (submitted?['documents'] as Map?)?.keys,
        containsAll(['id', 'selfie']),
      );
      expect(find.byType(AlertDialog), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
    },
  );
  testWidgets('rider completes shared application fields and company terms', (
    tester,
  ) async {
    Map<String, dynamic>? submitted;
    var optionsAttempts = 0;
    final client = BackendClient(
      transport: MockClient((request) async {
        if (request.url.path.endsWith('registration-postal-code')) {
          return http.Response('{"postal_code":"4020","status":"found"}', 200);
        }
        if (request.url.path.endsWith('registration-options')) {
          if (++optionsAttempts == 1) {
            return http.Response('{"message":"Temporary outage"}', 503);
          }
          return http.Response(
            jsonEncode({
              'vehicle_types': [
                {'slug': 'two_wheels', 'name': 'Two wheels'},
              ],
              'hubs': [
                {
                  'id': 7,
                  'company': 'Test Logistics',
                  'location': 'Famy, Laguna',
                  'province': 'Laguna',
                  'eligible': true,
                  'available_vehicle_types': [],
                },
              ],
            }),
            200,
          );
        }
        if (request.url.path.endsWith('registration-terms')) {
          return http.Response(
            '{"title":"Terms","content":"Published terms."}',
            200,
          );
        }
        if (request.url.path.endsWith('registration-username')) {
          return http.Response('{"available":true}', 200);
        }
        if (request.url.path.endsWith('registration-send-code')) {
          return http.Response('{"sent":true}', 200);
        }
        if (request.url.path.endsWith('registration-verify-code')) {
          return http.Response('{"email_token":"rider-email-proof"}', 200);
        }
        if (request.url.path.endsWith('register-buyer')) {
          submitted = Map<String, dynamic>.from(
            jsonDecode(request.body) as Map,
          );
          return http.Response('{"submitted":true,"status":"pending"}', 200);
        }
        return http.Response('[]', 200);
      }),
    );
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.current,
        home: BuyerRegistrationScreen(
          accountType: 'rider',
          client: client,
          addressService: _AddressDirectory(),
          documentPicker: (kind) async =>
              XFile.fromData(Uint8List.fromList([1, 2, 3]), name: '$kind.png'),
        ),
      ),
    );
    await tester.pumpAndSettle();
    Future<void> tap(String text) async {
      await tester.ensureVisible(find.text(text));
      await tester.pumpAndSettle();
      await tester.tap(find.text(text));
      await tester.pumpAndSettle();
    }

    Future<void> select(String label, String value) async {
      await tap(label);
      await tester.tap(find.text(value).last);
      await tester.pumpAndSettle();
    }

    Future<void> enter(String label, String value) async {
      final field = find.widgetWithText(AuthTextField, label);
      await tester.ensureVisible(field);
      await tester.enterText(field, value);
    }

    expect(find.text('Step 1 of 7 · Address'), findsOneWidget);
    await select('Region', 'CALABARZON');
    await select('Province', 'Laguna');
    await select('City / municipality', 'Famy');
    await select('Barangay', 'Salangbato');
    final postalField = tester.widget<TextFormField>(
      find.widgetWithText(TextFormField, 'Postal code'),
    );
    expect(postalField.controller!.text, '4020');
    expect(
      tester
          .widget<EditableText>(
            find.descendant(
              of: find.widgetWithText(TextFormField, 'Postal code'),
              matching: find.byType(EditableText),
            ),
          )
          .readOnly,
      isTrue,
    );
    await tap('Continue');
    await tap('Test Logistics');
    await tap('Famy, Laguna');
    await tap('Continue');
    expect(find.text('Step 3 of 7 · License'), findsOneWidget);
    await enter('Driver’s license number', 'A12-34-123456');
    await tap('Select license expiry');
    await tap('OK');
    await tap('Driver’s license');
    await tap('NBI clearance');
    await tap('Selfie with License');
    await tap('Continue');
    await tap('Two wheels');
    await enter('Vehicle brand', 'Honda');
    await enter('Vehicle model', 'Click');
    await enter('Plate number', 'ABC1234');
    await tap('Official receipt (OR)');
    await tap('Certificate of registration (CR)');
    await tap('Continue');
    await enter('First name', 'Courier');
    await enter('Last name', 'Applicant');
    await tap('I have no middle name');
    await select('Sex', 'Female');
    await tap('Date of birth (18 or older)');
    await tap('OK');
    await tap('Continue');
    await tester.enterText(
      find.byKey(const ValueKey('buyer-email')),
      'rider@gmail.com',
    );
    await enter('Mobile number', '09123456789');
    await tap('Send verification code');
    await enter('6-digit email code', '123456');
    await tap('Verify email');
    await tap('Continue');
    await enter('Username', 'testrider123');
    await tester.enterText(
      find.byKey(const ValueKey('buyer-password')),
      'TestPass123!',
    );
    await tester.enterText(
      find.byKey(const ValueKey('buyer-confirmation')),
      'TestPass123!',
    );
    await tap('Read rider terms');
    await tap('I have read the terms');
    await tap('I accept the rider terms and conditions.');
    await tap('Read company terms');
    await tap('I have read the terms');
    await tap('I accept the terms of Test Logistics.');
    await tap('Submit application');
    expect(optionsAttempts, 2);
    expect(submitted?['postal_code'], '4020');
    expect(submitted?['account_type'], 'rider');
    expect(submitted?['flow'], 'website');
    expect(submitted?['logistics_hub_id'], 7);
    expect(submitted?['vehicle_ownership'], 'own');
    expect(submitted?['company_terms'], true);
    expect(submitted?['email_token'], 'rider-email-proof');
    expect(
      (submitted?['documents'] as Map).keys,
      containsAll(['license', 'nbi', 'or', 'cr', 'selfie']),
    );
    expect(find.byType(AlertDialog), findsOneWidget);
    await tester.pumpWidget(const SizedBox.shrink());
  });
}
