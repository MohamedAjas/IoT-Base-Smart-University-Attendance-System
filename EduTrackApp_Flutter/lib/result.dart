// lib/result_screen.dart
import 'package:flutter/material.dart';
import 'package:edutruck/utils/colors.dart';
import 'package:edutruck/upload.dart'; // For UploadScreen
import 'package:edutruck/home_screen.dart'; // For HomeScreen and SettingsScreen/HistoryScreen placeholders
// import 'package:edutruck/history.dart'; // If HistoryScreen moves to its own file

// Model for Subject Data (This should be the primary definition)
class SubjectResult {
  final String code;
  final String name;
  final String eligibilityStatus;

  SubjectResult({
    required this.code,
    required this.name,
    required this.eligibilityStatus,
  });
}

class ResultScreen extends StatefulWidget {
  final String userRegisterNumber;
  final String currentCsvFileName;
  final List<SubjectResult> subjectData;

  const ResultScreen({
    super.key,
    required this.userRegisterNumber,
    required this.currentCsvFileName,
    required this.subjectData,
  });

  @override
  State<ResultScreen> createState() => _ResultScreenState();
}

class _ResultScreenState extends State<ResultScreen> {
  final int _thisScreenTabIndex = 3; // Learn/Result is tab 3
  late int _currentIndex;

  @override
  void initState() {
    super.initState();
    _currentIndex = _thisScreenTabIndex; // Set initial active tab for ResultScreen
  }

  void _onBottomNavItemTapped(int index) {
    // Handle FAB navigation to UploadScreen
    if (index == 2) {
      print('FAB (+) tapped from ResultScreen, navigating to UploadScreen');
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => UploadScreen(userRegisterNumber: widget.userRegisterNumber)),
      );
      return;
    }

    // If tapping the current screen's tab (Learn/Result tab)
    if (index == _thisScreenTabIndex) {
      print("Already on Learn/Result tab (index 3).");
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
      case 0: // Home
        print('Home tapped from ResultScreen, navigating to HomeScreen');
        nextPage = HomeScreen(userRegisterNumber: widget.userRegisterNumber);
        break;
      case 1: // History
        print('History tapped from ResultScreen. Navigating to HistoryScreen.');
        // TODO: Implement HistoryScreen properly
        nextPage = HistoryScreen(userRegisterNumber: widget.userRegisterNumber);
        // setState(() { _currentIndex = index; });
        // return;
        break;
    // case 2: FAB handled above
      case 3: // Learn/Result - Handled by the check above
        break;
      case 4: // Settings
        print('Settings tapped from ResultScreen. Navigating to SettingsScreen.');
        // TODO: Ensure SettingsScreen is robust
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
      setState(() { _currentIndex = index; });
    }
  }


  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.resultHeaderBackground,
      body: SafeArea(
        child: Column(
          children: [
            _buildHeader(),
            _buildCsvFileButton(),
            Expanded(
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.only(top: 20, left: 20, right: 20),
                decoration: const BoxDecoration(
                  color: AppColors.white,
                  borderRadius: BorderRadius.only(
                    topLeft: Radius.circular(30.0),
                    topRight: Radius.circular(30.0),
                  ),
                ),
                child: Column(
                  children: [
                    _buildTableHeaders(),
                    const SizedBox(height: 10),
                    Expanded(child: _buildResultsList()),
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
                  style: TextStyle(fontSize: 14, color: AppColors.secondaryText, fontWeight: FontWeight.normal),
                ),
                Text(
                  widget.userRegisterNumber,
                  style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600, color: AppColors.primaryText),
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
                    color: AppColors.white,
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.grey.withOpacity(0.2),
                        spreadRadius: 1,
                        blurRadius: 3,
                        offset: const Offset(0, 1),
                      )
                    ]
                ),
                child: Icon(Icons.notifications_none_outlined, color: AppColors.notificationBellColor, size: 28),
              ),
              Positioned(
                right: 8,
                top: 8,
                child: Container(
                  padding: const EdgeInsets.all(2),
                  decoration: BoxDecoration(
                      color: AppColors.notificationDotColor,
                      shape: BoxShape.circle,
                      border: Border.all(color: AppColors.white, width: 1.5)
                  ),
                  constraints: const BoxConstraints(minWidth: 8, minHeight: 8),
                ),
              ),
            ],
          )
        ],
      ),
    );
  }

  Widget _buildCsvFileButton() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 15.0),
      child: OutlinedButton(
        onPressed: () {
          print('${widget.currentCsvFileName} button tapped on ResultScreen');
        },
        style: OutlinedButton.styleFrom(
            backgroundColor: AppColors.white,
            padding: const EdgeInsets.symmetric(vertical: 18),
            side: const BorderSide(color: AppColors.resultButtonBorder, width: 2),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15.0)),
            elevation: 2,
            shadowColor: AppColors.resultButtonBorder.withOpacity(0.3)
        ),
        child: Center(
          child: Text(
            widget.currentCsvFileName.isEmpty ? "No CSV Selected" : widget.currentCsvFileName,
            style: const TextStyle(
              color: AppColors.resultButtonText,
              fontSize: 16,
              fontWeight: FontWeight.bold,
            ),
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ),
    );
  }

  Widget _buildTableHeaders() {
    return Padding(
      padding: const EdgeInsets.only(top: 8.0, bottom: 8.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          const Text(
            'Subject Code',
            style: TextStyle(
              color: AppColors.resultTableHeaderText,
              fontWeight: FontWeight.bold,
              fontSize: 15,
            ),
          ),
          const Text(
            'Eligibility Status',
            style: TextStyle(
              color: AppColors.resultTableHeaderText,
              fontWeight: FontWeight.bold,
              fontSize: 15,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildResultsList() {
    final dataToDisplay = widget.subjectData;

    if (dataToDisplay.isEmpty) {
      return const Center(child: Padding(
        padding: EdgeInsets.all(16.0),
        child: Text("No result data to display for this context.", style: TextStyle(color: AppColors.secondaryText, fontSize: 16)),
      ));
    }

    return ListView.separated(
      itemCount: dataToDisplay.length,
      itemBuilder: (context, index) {
        final subject = dataToDisplay[index];
        bool isEligible = subject.eligibilityStatus.toLowerCase() == 'eligible';
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 12.0),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                flex: 3,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      subject.code,
                      style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.primaryText, fontSize: 14),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subject.name,
                      style: const TextStyle(color: AppColors.secondaryText, fontSize: 13),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                flex: 2,
                child: Text(
                  subject.eligibilityStatus,
                  textAlign: TextAlign.right,
                  style: TextStyle(
                    color: isEligible ? AppColors.resultEligibleText : AppColors.resultNotEligibleText,
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
              ),
            ],
          ),
        );
      },
      separatorBuilder: (context, index) => const Divider(color: AppColors.resultDivider, height: 1),
    );
  }

  Widget _buildCustomBottomNavigationBar() {
    final Color activeIconColor = AppColors.authAccentRed;
    final Color inactiveIconColor = AppColors.authAccentRed.withOpacity(0.7);

    return Container(
      height: 70,
      decoration: BoxDecoration(
        color: const Color(0xFFF0F0F0),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.08),
            blurRadius: 8,
            offset: const Offset(0, -2),
          ),
        ],
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
              boxShadow: [
                BoxShadow(
                  color: fabColor.withOpacity(0.4),
                  blurRadius: 8,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: const Icon(
              Icons.add,
              color: AppColors.white,
              size: 30,
            ),
          ),
        ),
      ),
    );
  }
}