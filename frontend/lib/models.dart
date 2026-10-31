class NotificationItem {
  final int id;
  final String type;
  final String message;
  final String? sentAt;

  const NotificationItem({
    required this.id,
    required this.type,
    required this.message,
    this.sentAt,
  });

  factory NotificationItem.fromJson(Map<String, dynamic> json) => NotificationItem(
    id: json['id'] as int,
    type: json['type'] as String,
    message: json['message'] as String,
    sentAt: json['sent_at'] as String?,
  );
}

class FailedAlertItem {
  final int id;
  final String type;
  final String channel;
  final int? applicationId;
  final int attempts;
  final String reason;
  final String createdAt;

  const FailedAlertItem({
    required this.id,
    required this.type,
    required this.channel,
    required this.applicationId,
    required this.attempts,
    required this.reason,
    required this.createdAt,
  });

  factory FailedAlertItem.fromJson(Map<String, dynamic> json) => FailedAlertItem(
    id: json['id'] as int,
    type: json['type'] as String,
    channel: json['channel'] as String,
    applicationId: json['application_id'] as int?,
    attempts: json['attempts'] as int,
    reason: json['reason'] as String,
    createdAt: json['created_at'] as String,
  );

  String get friendlyReason {
    switch (reason) {
      case 'INVALID_RECIPIENT_ADDRESS':
        return 'Invalid email address on file';
      case 'RETRIES_EXHAUSTED':
        return 'Delivery failed after all retries';
      default:
        return reason;
    }
  }
}