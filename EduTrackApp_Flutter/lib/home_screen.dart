// lib/home_screen.dart
import 'package:flutter/material.dart';
import 'package:edutruck/utils/colors.dart';
import 'package:edutruck/upload.dart';
import 'package:edutruck/result.dart'; // Import for ResultScreen and SubjectResult
import 'package:edutruck/history.dart'; // Create this file for HistoryScreen eventually

// Placeholder for SettingsScreen (keep it here or move to its own file)
class SettingsScreen extends StatelessWidget {
  final String userRegisterNumber;
  const SettingsScreen({super.key, required this.userRegisterNumber});
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text("Settings")),
    body: Center(child: Text("Settings Screen for $userRegisterNumber")),
  );
}
// Placeholder HistoryScreen for navigation structure
class HistoryScreen extends StatelessWidget {
  final String userRegisterNumber;
  const HistoryScreen({super.key, required this.userRegisterNumber});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text("History")),
      body: Center(child: Text("History Screen for $userRegisterNumber. Implement me!")),
      // Potentially add its own bottomNavigationBar if it's a main tab screen
    );
  }
}


class HomeScreen extends StatefulWidget {
  final String userRegisterNumber;

  const HomeScreen({
    super.key,
    required this.userRegisterNumber,
  });

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final int _thisScreenTabIndex = 0; // HomeScreen is always tab 0
  late int _currentIndex;

  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _currentIndex = _thisScreenTabIndex; // Set the initial active tab for HomeScreen
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _onBottomNavItemTapped(int index) {
    // Handle FAB navigation to UploadScreen
    if (index == 2) {
      print('FAB (+) tapped from Home, navigating to UploadScreen');
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => UploadScreen(userRegisterNumber: widget.userRegisterNumber)),
      );
      return;
    }

    // If tapping the current screen's tab (Home tab)
    if (index == _thisScreenTabIndex) {
      print("Already on Home tab (index 0).");
      // Ensure visual state is correct if it was somehow different
      if (_currentIndex != _thisScreenTabIndex) {
        setState(() {
          _currentIndex = _thisScreenTabIndex;
        });
      }
      return;
    }

    // Handle navigation to other screens or placeholder tabs
    Widget? nextPage;
    bool useCustomNavigation = false;

    switch (index) {
      case 0: // Home - Handled by the check above
        break;
      case 1: // History tab
        print('History tapped from Home. Navigating to HistoryScreen.');
        // TODO: Implement HistoryScreen properly
        nextPage = HistoryScreen(userRegisterNumber: widget.userRegisterNumber);
        // For now, let's allow navigation to a placeholder
        // setState(() { _currentIndex = index; });
        // return;
        break;
    // case 2: FAB handled above
      case 3: // Learn/Result tab
        print('Learn/Result tapped from Home, navigating to ResultScreen');
        // For ResultScreen, pass placeholder data from Home's context
        final List<SubjectResult> homeContextResults = [
          // Example: You might fetch latest results or a summary
          // SubjectResult(code: "SUM001", name: "Overall Summary", eligibilityStatus: "Eligible"),
        ];
        const String homeContextCsv = "Overall_Results.csv"; // Placeholder

        nextPage = ResultScreen(
          userRegisterNumber: widget.userRegisterNumber,
          currentCsvFileName: homeContextCsv,
          subjectData: homeContextResults,
        );
        break;
      case 4: // Settings tab
        print('Settings tapped from Home. Navigating to SettingsScreen.');
        // TODO: Ensure SettingsScreen is robust or move to its own file
        nextPage = SettingsScreen(userRegisterNumber: widget.userRegisterNumber);
        // setState(() { _currentIndex = index; });
        // return;
        break;
      default:
        return;
    }

    if (nextPage != null) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => nextPage!),
      );
    } else if (!useCustomNavigation && index != _currentIndex) {
      // Fallback for tabs that don't navigate but should show as active
      setState(() { _currentIndex = index; });
    }
  }


  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.homeHeaderBackground,
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _buildHeader(),
            _buildSearchBar(),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.only(top: 10.0, bottom: 20),
                child: Column(
                  children: [
                    _buildMainStatsCard(),
                    const SizedBox(height: 20),
                    _buildActionCards(),
                    const SizedBox(height: 20),
                    _buildLineChartCard(),
                    const SizedBox(height: 20),
                    _buildCalendarCard(),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: _buildCustomBottomNavigationBar(),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.only(left: 20.0, right: 20.0, top: 20.0, bottom: 10.0),
      child: Row(
        children: [
          CircleAvatar(
            radius: 28,
            backgroundImage: const AssetImage('images/img.png'),
          ),
          const SizedBox(width: 15),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Welcome Back',
                  style: TextStyle(fontSize: 14, color: AppColors.white, fontWeight: FontWeight.w300),
                ),
                Text(
                  widget.userRegisterNumber,
                  style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600, color: AppColors.white),
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          Stack(
            alignment: Alignment.topRight,
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                    color: AppColors.white.withOpacity(0.85),
                    shape: BoxShape.circle,
                    boxShadow: [ BoxShadow(color: Colors.black.withOpacity(0.1), spreadRadius: 1, blurRadius: 3)]
                ),
                child: Icon(Icons.notifications_none_outlined, color: AppColors.secondaryText, size: 28),
              ),
              Positioned(
                right: 8, top: 8,
                child: Container(
                  padding: const EdgeInsets.all(1.5),
                  decoration: BoxDecoration(
                      color: AppColors.notificationDotColor,
                      shape: BoxShape.circle,
                      border: Border.all(color: AppColors.white.withOpacity(0.85), width: 1.5)
                  ),
                  constraints: const BoxConstraints(minWidth: 10, minHeight: 10),
                  child: const Text("1", style: TextStyle(color: Colors.white, fontSize: 6, fontWeight: FontWeight.bold), textAlign: TextAlign.center,),
                ),
              ),
            ],
          )
        ],
      ),
    );
  }

  Widget _buildSearchBar() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 15.0),
      child: TextField(
        controller: _searchController,
        decoration: InputDecoration(
          hintText: 'Search....',
          hintStyle: TextStyle(color: AppColors.secondaryText.withOpacity(0.7)),
          prefixIcon: Icon(Icons.search, color: AppColors.searchIconColor.withOpacity(0.7)),
          filled: true,
          fillColor: AppColors.searchBarBackground,
          contentPadding: const EdgeInsets.symmetric(vertical: 15.0),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(30.0),
            borderSide: const BorderSide(color: AppColors.searchBarBorder, width: 1.0),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(30.0),
            borderSide: BorderSide(color: AppColors.searchBarBorder.withOpacity(0.5), width: 1.0),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(30.0),
            borderSide: const BorderSide(color: AppColors.authAccentRed, width: 1.5),
          ),
        ),
      ),
    );
  }

  Widget _buildMainStatsCard() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20.0),
      child: Card(
        elevation: 4, margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(25.0)),
        color: AppColors.mainStatsCardBackground.withOpacity(0.9),
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: SizedBox(
            height: 180,
            child: Row(
              children: [
                Expanded(
                  flex: 2,
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        height: 100, width: 100,
                        decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: Colors.red.shade400, width: 2)),
                        child: Center(child: Text("Gauge", style: TextStyle(color: Colors.grey.shade600))),
                      ),
                      const SizedBox(height: 8),
                      Text("878", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.primaryText.withOpacity(0.8)))
                    ],
                  ),
                ),
                const VerticalDivider(width: 20, thickness: 1),
                Expanded(
                  flex: 3,
                  child: Container(
                    decoration: BoxDecoration(border: Border.all(color: Colors.grey.shade300, width: 1), borderRadius: BorderRadius.circular(8)),
                    child: Center(child: Text("Stacked Bar Chart", textAlign: TextAlign.center, style: TextStyle(color: Colors.grey.shade600))),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildActionCards() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20.0),
      child: Row(
        children: [
          Expanded(child: _buildActionCardItem(Icons.volume_up_outlined, "Alarm", AppColors.alarmCardStart, AppColors.alarmCardEnd)),
          const SizedBox(width: 15),
          Expanded(child: _buildActionCardItem(Icons.bar_chart_rounded, "Grades", AppColors.gradesCardStart, AppColors.gradesCardEnd)),
        ],
      ),
    );
  }

  Widget _buildActionCardItem(IconData icon, String label, Color color1, Color color2) {
    return Card(
      elevation: 3,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20.0)),
      child: InkWell(
        onTap: () { print("$label card tapped"); },
        borderRadius: BorderRadius.circular(20.0),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 20.0, horizontal: 10.0),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20.0),
            gradient: LinearGradient(colors: [color1, color2], begin: Alignment.topLeft, end: Alignment.bottomRight),
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 30, color: AppColors.primaryText.withOpacity(0.7)),
              const SizedBox(height: 8),
              Text(label, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: AppColors.primaryText.withOpacity(0.9))),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildLineChartCard() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20.0),
      child: Card(
        elevation: 3, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20.0)),
        color: AppColors.lineChartCardBackground.withOpacity(0.9),
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Container(
            height: 150,
            decoration: BoxDecoration(border: Border.all(color: Colors.grey.shade300, width: 1), borderRadius: BorderRadius.circular(8)),
            child: Center(child: Text("Line Chart Placeholder", style: TextStyle(color: Colors.grey.shade600))),
          ),
        ),
      ),
    );
  }

  Widget _buildCalendarCard() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20.0),
      child: Card(
        elevation: 3, clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20.0)),
        child: Stack(
          alignment: Alignment.center,
          children: [
            Container(
              height: 180,
              decoration: BoxDecoration(
                image: DecorationImage(
                    image: const AssetImage('images/calendar.png'), // TODO: Add placeholder bg
                    fit: BoxFit.cover,
                    colorFilter: ColorFilter.mode(Colors.black.withOpacity(0.1), BlendMode.darken)
                ),
                borderRadius: BorderRadius.circular(20.0),
              ),
            ),
            Positioned(
              bottom: 65,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                decoration: BoxDecoration(color: AppColors.calendarLabelBackground, borderRadius: BorderRadius.circular(15)),
                child: const Text("Calendar", style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
              ),
            ),
          ],
        ),
      ),
    );
  }

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