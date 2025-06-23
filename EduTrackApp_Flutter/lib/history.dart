// lib/history.dart
import 'package:flutter/material.dart';
import 'package:edutruck/utils/colors.dart';
import 'package:edutruck/home_screen.dart';    // For HomeScreen, SettingsScreen
import 'package:edutruck/upload.dart';         // For UploadScreen
import 'package:edutruck/result.dart';         // For ResultScreen and SubjectResult Model

// Note: SubjectResult is defined in result.dart (as per previous updates)
// SettingsScreen is defined in home_screen.dart (or could be its own file)

class HistoryScreen extends StatefulWidget {
  final String userRegisterNumber;
  const HistoryScreen({super.key, required this.userRegisterNumber});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  // HistoryScreen corresponds to tab index 1
  final int _thisScreenTabIndex = 1;
  late int _currentIndex;

  // Sample data for navigating to ResultScreen from History (if needed)
  final List<SubjectResult> _sampleSubjectDataForNavigation = [
     SubjectResult(code: 'HIST_NAV_001', name: 'Sample Subject from History', eligibilityStatus: 'Eligible'),
  ];
  final String _sampleCsvFileNameForNavigation = "FROM_HISTORY_CONTEXT.CSV";

  @override
  void initState() {
    super.initState();
    _currentIndex = _thisScreenTabIndex; // Set initial active tab
  }

  void _onBottomNavItemTapped(int index) {
    // Handle FAB navigation to UploadScreen first if it's tapped
    if (index == 2) {
      print('HistoryScreen: FAB (+) tapped, navigating to UploadScreen');
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => UploadScreen(userRegisterNumber: widget.userRegisterNumber)),
      );
      return;
    }

    // If tapping the current screen's tab (History tab)
    if (index == _thisScreenTabIndex) {
      print("HistoryScreen: Already on History tab (index 1).");
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
        print('HistoryScreen: Home tapped, navigating to HomeScreen');
        nextPage = HomeScreen(userRegisterNumber: widget.userRegisterNumber);
        break;
    // case 1: History - Handled by the check above
    // case 2: FAB - Handled by the check above
      case 3: // Learn/Result
        print('HistoryScreen: Learn/Result tapped, navigating to ResultScreen');
        nextPage = ResultScreen(
          userRegisterNumber: widget.userRegisterNumber,
          currentCsvFileName: _sampleCsvFileNameForNavigation, // Provide relevant context
          subjectData: _sampleSubjectDataForNavigation,        // Provide relevant context
        );
        break;
      case 4: // Settings
        print('HistoryScreen: Settings tapped, navigating to SettingsScreen');
        // Assuming SettingsScreen is defined in home_screen.dart or its own file
        nextPage = SettingsScreen(userRegisterNumber: widget.userRegisterNumber);
        break;
      default:
        print("HistoryScreen: Tapped an unhandled tab index: $index");
        return;
    }

    if (nextPage != null) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => nextPage!),
      );
    }
    // No need for an else to setState for _currentIndex if navigating away,
    // as the new screen will manage its own _currentIndex.
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('History'),
        backgroundColor: AppColors.authAccentRed, // Or any color you prefer
        // To prevent the back arrow when navigating with pushReplacement from other main screens:
        automaticallyImplyLeading: false,
      ),
      body: Center( // Replace with your actual history content
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                'Upload History for ${widget.userRegisterNumber}',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 20),
              const Text(
                '(This is where you would list previously uploaded files or analyzed results summaries.)',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 16, color: AppColors.secondaryText),
              ),
              // TODO: Implement actual history listing UI
            ],
          ),
        ),
      ),
      bottomNavigationBar: _buildCustomBottomNavigationBar(),
    );
  }

  // --- Bottom Navigation Bar Methods ---
  Widget _buildCustomBottomNavigationBar() {
    final Color activeIconColor = AppColors.authAccentRed;
    final Color inactiveIconColor = AppColors.authAccentRed.withOpacity(0.7);

    return Container(
      height: 70,
      decoration: BoxDecoration(
        color: const Color(0xFFF0F0F0), // Light grey background for the bar
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
        borderRadius: BorderRadius.circular(20), // For ripple effect shape
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
          onTap: () => _onBottomNavItemTapped(2), // Index 2 for the FAB
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