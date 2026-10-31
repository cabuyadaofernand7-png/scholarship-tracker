import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models.dart';

// Only the API address lives in the client. No keys or secrets, ever.
const String kApiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://127.0.0.1:8000/api',
);

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  const ApiException(this.message, {this.statusCode});

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({http.Client? client}) : _client = client ?? http.Client();

  final http.Client _client;
  static const _headers = {'Accept': 'application/json'};
  static const _timeout = Duration(seconds: 10);

  Future<Map<String, dynamic>> _request(Future<http.Response> Function() send) async {
    try {
      final res = await send().timeout(_timeout);

      Map<String, dynamic> body = {};
      try {
        body = jsonDecode(res.body) as Map<String, dynamic>;
      } catch (_) {}

      if (res.statusCode >= 200 && res.statusCode < 300) return body;
      if (res.statusCode == 404) {
        throw const ApiException('Not found.', statusCode: 404);
      }
      if (res.statusCode == 409) {
        throw ApiException((body['message'] as String?) ?? 'Conflict.', statusCode: 409);
      }
      if (res.statusCode == 422) {
        throw const ApiException('The server rejected that input.', statusCode: 422);
      }
      throw ApiException(
        'Server error (${res.statusCode}). Please try again later.',
        statusCode: res.statusCode,
      );
    } on TimeoutException {
      throw const ApiException('The server took too long to respond.');
    } on http.ClientException {
      throw const ApiException('Cannot reach the server. Check that it is running and try again.');
    }
  }

  Future<List<NotificationItem>> getNotifications(int applicantId) async {
    final body = await _request(() => _client.get(
      Uri.parse('$kApiBaseUrl/applicants/$applicantId/notifications'),
      headers: _headers,
    ));
    final data = (body['data'] as List<dynamic>?) ?? [];
    return data.map((e) => NotificationItem.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<FailedAlertItem>> getFailedAlerts() async {
    final body = await _request(() => _client.get(
      Uri.parse('$kApiBaseUrl/staff/failed-alerts'),
      headers: _headers,
    ));
    final data = (body['data'] as List<dynamic>?) ?? [];
    return data.map((e) => FailedAlertItem.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> resolveFailedAlert(int id) async {
    await _request(() => _client.post(
      Uri.parse('$kApiBaseUrl/staff/failed-alerts/$id/resolve'),
      headers: _headers,
    ));
  }
}