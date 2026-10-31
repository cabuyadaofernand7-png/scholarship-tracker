import 'package:flutter/material.dart';

import 'api/api_client.dart';
import 'screens/staff_failed_alerts_screen.dart';
import 'screens/student_notifications_screen.dart';

void main() => runApp(const ScholarshipTrackerApp());

class ScholarshipTrackerApp extends StatelessWidget {
  const ScholarshipTrackerApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Scholarship Requirement Tracker',
      theme: ThemeData(colorSchemeSeed: Colors.indigo, useMaterial3: true),
      debugShowCheckedModeBanner: false,
      home: const HomeShell(),
    );
  }
}

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  final _api = ApiClient();
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final screens = [
      StudentNotificationsScreen(api: _api),
      StaffFailedAlertsScreen(api: _api, key: UniqueKey()),
    ];

    return Scaffold(
      appBar: AppBar(title: const Text('Scholarship Requirement Tracker')),
      body: Column(
        children: [
          Container(
            width: double.infinity,
            color: Theme.of(context).colorScheme.secondaryContainer,
            padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 16),
          ),
          Expanded(child: screens[_index]),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.notifications), label: 'Student'),
          NavigationDestination(icon: Icon(Icons.support_agent), label: 'Staff'),
        ],
      ),
    );
  }
}