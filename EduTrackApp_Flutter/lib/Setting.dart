// lib/settings_screen.dart
import 'package:flutter/material.dart';
import 'package:edutruck/utils/colors.dart';
import 'package:edutruck/home_screen.dart';    // For HomeScreen
import 'package:edutruck/upload.dart';         // For UploadScreen
import 'package:edutruck/result.dart';         // For ResultScreen & SubjectResult model

// Note: SubjectResult model is defined in result.dart or its own file.

class SettingsScreen extends StatefulWidget {
  final String userRegisterNumber;
  const SettingsScreen({super.key, required this.userRegisterNumber});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  // SettingsScreen corresponds to tab index 4
  final int _thisScreenTabIndex = 4;
  late int _currentIndex;

  // Sample data for navigating to ResultScreen from Settings (if needed for some reason)
  final List<SubjectResult> _sampleSubjectDataForNavigation = [
    // SubjectResult(code: 'SET_NAV_001', name: 'Sample Subject from Settings', eligibilityStatus: 'Eligible'),
  ];
  final String _sampleCsvFileNameForNavigation = "FROM_SETTINGS_CONTEXT.CSV";


  @override
  void initState() {
    super.initState();
    _currentIndex = _thisScreenTabIndex; // Set initial active tab
  }

  void _onBottomNavItemTapped(int index) {
    // Handle FAB navigation to UploadScreen first if it's tapped
    if (index == 2) {
      print('SettingsScreen: FAB (+) tapped, navigating to UploadScreen');
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => UploadScreen(userRegisterNumber: widget.userRegisterNumber)),
      );
      return;
    }

    // If tapping the current screen's tab (Settings tab)
    if (index == _thisScreenTabIndex) {
      print("SettingsScreen: Already on Settings tab (index 4).");
      if (_currentIndex != _thisScreenTabIndex) { // Ensure visual state is correct
        setState(() {
          _currentIndex = _thisScreenTabIndex;
        });
      }
      return;
    }

    // Handle navigation to other screens
    Widget? nextPage;
    switch (index) {
      case 0: // Home
        print('SettingsScreen: Home tapped, navigating to HomeScreen');
        nextPage = HomeScreen(userRegisterNumber: widget.userRegisterNumber);
        break;
      case 1: // History
        print('SettingsScreen: History tapped, navigating to HistoryScreen');
        nextPage = HistoryScreen(userRegisterNumber: widget.userRegisterNumber);
        break;
    // case 2: FAB - Handled by the check above
      case 3: // Learn/Result
        print('SettingsScreen: Learn/Result tapped, navigating to ResultScreen');
        nextPage = ResultScreen(
          userRegisterNumber: widget.userRegisterNumber,
          currentCsvFileName: _sampleCsvFileNameForNavigation, // Provide relevant context
          subjectData: _sampleSubjectDataForNavigation,        // Provide relevant context
        );
        break;
    // case 4: Settings - Handled by the check above
      default:
        print("SettingsScreen: Tapped an unhandled tab index: $index");
        return;
    }

    if (nextPage != null) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => nextPage!),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Settings'),
        backgroundColor: AppColors.authAccentRed, // Or your preferred color
        automaticallyImplyLeading: false, // Prevents back arrow with pushReplacement
      ),
      body: Center( // Replace with your actual settings content
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                'App Settings for ${widget.userRegisterNumber}',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 20),
              const Text(
                '(Profile settings, theme options, notifications, logout, etc.)',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 16, color: AppColors.secondaryText),
              ),
              // TODO: Implement actual settings UI components
              const SizedBox(height: 30),
              ElevatedButton.icon(
                icon: const Icon(Icons.logout),
                label: const Text('Logout (Placeholder)'),
                onPressed: () {
                  // TODO: Implement logout logic
                  // e.g., Navigator.pushAndRemoveUntil(context, MaterialPageRoute(builder: (context) => WelcomeScreen()), (route) => false);
                  print("Logout button tapped");
                  ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Logout functionality to be implemented!'))
                  );
                },
                style: ElevatedButton.styleFrom(backgroundColor: AppColors.errorRed),
              )
            ],
          ),
        ),
      ),
      bottomNavigationBar: _buildCustomBottomNavigationBar(),
    );
  }

  // --- Bottom Navigation Bar Methods (Copied from other screens) ---
  Widget _buildCustomBottomNavigationBar() {
    final Color activeIconColor = AppColors.authAccentRed;
    final Color inactiveIconColor = AppColors.authAccentRed.withOpacity(0.7);

    return Container(
      height: 70,
      decoration: BoxDecoration(
        color: const Color(0xFFF0F0F0),
        boxShadow: [ BoxShadow(color: Colors.black.withOpacity(0.08), blurRadius: 8, offset: const Offset(0, -2)) ],
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: <Widget>[
          _buildNavItem(Icons.home_filled, "Home", 0, activeIconColor, inactiveIconColor),
          _buildNavItem(Icons.history_rounded, "History", 1, activeIconColor, inactiveIconColor),
          _buildFabNavItem(activeIconColor),
          _buildNavItem(Icons.school_rounded, "Learn", 3, activeIconColor, inactiveIconColor),
          _buildNavItem(Icons.settings_rounded, "Settings", 4, activeIconColor, inactiveIconColor),
        ],
      ),
    );
  }

  Widget _buildNavItem(IconData icon, String label, int index, Color activeColor, Color inactiveColor) {
    bool isActive = _currentIndex == index;
    final Color currentColor = isActive ? activeColor : inactiveColor;
    final double iconSize = isActive ? 28 : 26;

    return Expanded(
      child: InkWell(
        onTap: () => _onBottomNavItemTapped(index),
        borderRadius: BorderRadius.circular(20),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: iconSize, color: currentColor),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                fontSize: 10,
                color: currentColor,
                fontWeight: isActive ? FontWeight.w600 : FontWeight.normal,
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFabNavItem(Color fabColor) {
    return Expanded(
      child: Center(
        child: InkWell(
          onTap: () => _onBottomNavItemTapped(2),
          customBorder: const CircleBorder(),
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: fabColor,
              shape: BoxShape.circle,
              boxShadow: [ BoxShadow(color: fabColor.withOpacity(0.4), blurRadius: 8, offset: const Offset(0, 4)) ],
            ),
            child: const Icon(Icons.add, color: AppColors.white, size: 30),
          ),
        ),
      ),
    );
  }
}