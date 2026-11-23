/// Real earnings aggregation over a rider's completed deliveries/tasks —
/// plain Dart math over (date, fee) pairs already fetched/held elsewhere,
/// not a separate RPC. Deliberately decoupled from any specific task/
/// shipment model (just dates + fees) so both the real `Shipment`-backed
/// flow and the mock `RiderTask`-backed flow can reuse the same math.
class RiderEarnings {
  final double today;
  final double thisWeek;
  final double thisMonth;
  final int completedCount;

  /// One entry per of the last 7 days (oldest first), for the mini chart.
  final List<DailyEarnings> last7Days;

  const RiderEarnings({
    required this.today,
    required this.thisWeek,
    required this.thisMonth,
    required this.completedCount,
    required this.last7Days,
  });

  /// [entries] is one (completion date, fee earned) pair per completed
  /// task/delivery.
  factory RiderEarnings.fromEntries(
    Iterable<({DateTime date, double fee})> entries,
  ) {
    final now = DateTime.now();
    final startOfToday = DateTime(now.year, now.month, now.day);
    final startOfWeek = startOfToday.subtract(Duration(days: now.weekday - 1));
    final startOfMonth = DateTime(now.year, now.month, 1);

    double today = 0, thisWeek = 0, thisMonth = 0;
    var completedCount = 0;

    for (final entry in entries) {
      final day = DateTime(entry.date.year, entry.date.month, entry.date.day);
      completedCount++;

      if (!day.isBefore(startOfToday)) today += entry.fee;
      if (!day.isBefore(startOfWeek)) thisWeek += entry.fee;
      if (!day.isBefore(startOfMonth)) thisMonth += entry.fee;
    }

    return RiderEarnings(
      today: today,
      thisWeek: thisWeek,
      thisMonth: thisMonth,
      completedCount: completedCount,
      last7Days: dailySeries(entries, 7),
    );
  }

  /// The real per-day totals over the last [days] days (today included,
  /// oldest first) — used by the Earnings chart's 7-day/30-day filter.
  /// Recomputed client-side from the same already-fetched entries, no
  /// extra round trip per filter change.
  static List<DailyEarnings> dailySeries(
    Iterable<({DateTime date, double fee})> entries,
    int days,
  ) {
    final now = DateTime.now();
    final byDay = <DateTime, double>{
      for (var i = days - 1; i >= 0; i--)
        DateTime(now.year, now.month, now.day).subtract(Duration(days: i)): 0,
    };

    for (final entry in entries) {
      final day = DateTime(entry.date.year, entry.date.month, entry.date.day);
      if (byDay.containsKey(day)) {
        byDay[day] = (byDay[day] ?? 0) + entry.fee;
      }
    }

    return byDay.entries
        .map((e) => DailyEarnings(day: e.key, amount: e.value))
        .toList()
      ..sort((a, b) => a.day.compareTo(b.day));
  }
}

class DailyEarnings {
  final DateTime day;
  final double amount;

  const DailyEarnings({required this.day, required this.amount});
}
