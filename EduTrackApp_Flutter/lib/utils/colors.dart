// lib/utils/colors.dart
import 'package:flutter/material.dart';

class AppColors {
  AppColors._(); // Private constructor

  // --- Colors from Welcome Screen ---
  static const Color welcomePrimaryRed = Color(0xFFA23A39);
  static const Color welcomeLightOrange = Color(0xFFE88C78);
  static const Color welcomeButtonOutline = Color(0xFFD9A197);

  // --- Colors for Authentication Screens (Sign In/Sign Up) & similar backgrounds ---
  static const Color authBackground = Color(0xFFD3A088); // For top background of Sign In/Up/Upload
  static const Color authAccentRed = Color(0xFFC62828);  // For buttons, labels, active icons on these screens

  // --- Colors for TextFields & UI Elements ---
  static const Color textFieldUnderline = Color(0xFFBDBDBD);
  static const Color hintText = Color(0xFFA0A0A0);

  // --- Specific Colors for Upload Screen ---
  // authBackground can be used for the top part of UploadScreen
  // authAccentRed can be used for the FAB and active bottom nav item
  static const Color uploadDropzoneBorder = Color(0xFFBDBDBD);       // Grey for dotted border
  static const Color uploadDropzoneBackgroundStart = Color(0xFFE0E0E0); // Light grey for dropzone gradient
  static const Color uploadDropzoneBackgroundEnd = Color(0xFFF5F5F5);   // Lighter grey for dropzone gradient
  static const Color uploadButtonSave = Color(0xFF8A2120);        // A darker/specific red for the save button
  static const Color uploadButtonClear = Colors.black;             // upload_screen.dart used AppColors.black
  static const Color recentFilesText = authAccentRed;              // Using authAccentRed for "Recent" text

  // --- Common/General App Colors ---
  static const Color white = Colors.white;
  static const Color black = Colors.black;
  static const Color transparent = Colors.transparent;
  static const Color primaryText = Color(0xFF212121);         // For main body text
  static const Color secondaryText = Color(0xFF757575);       // For less important text, subtitles
  static const Color iconColor = Color(0xFF757575);           // General color for icons (can be overridden)
  static const Color errorRed = Color(0xFFD32F2F);           // For error messages or indicators
  static const Color grey = Colors.grey;                      // General Material grey (often Colors.grey.shadeX00 for specific shades)


// In lib/utils/colors.dart
// ... existing colors ...
  static const Color resultHeaderBackground = Color(0xFFF5EFEA); // Light beige for the top section
  static const Color resultButtonBorder = authAccentRed; // Red for the CSV button border
  static const Color resultButtonText = Colors.black;
  static const Color resultEligibleText = Colors.blue; // Or a specific blue like Color(0xFF3B5998)
  static const Color resultNotEligibleText = authAccentRed; // Red for "Not Eligible"
  static const Color resultTableHeaderText = authAccentRed;
  static const Color resultDivider = Color(0xFFE0E0E0); // Light grey for dividers
  static const Color notificationBellColor = Color(0xFF757575); // Grey for the bell
  static const Color notificationDotColor = Colors.orange; // For the dot on the bell

  static const Color historyItemBackground1Start = Color(0xFF4A56E2);
  static const Color historyItemBackground1End = Color(0xFFB066FE);
  static const Color historyItemBackground2Start = Color(0xFF20BF55);
  static const Color historyItemBackground2End = Color(0xFF01BAEF);
// We can use the colors directly in the dummy data for now.


// In lib/utils/colors.dart
// ... existing colors ...
  static const Color homeHeaderBackground = authBackground; // Reuse existing or define new e.g. Color(0xFFE5CDBF)
  static const Color searchBarBackground = Colors.white;
  static const Color searchBarBorder = Color(0xFFDCDCDC);
  static const Color searchIconColor = AppColors.secondaryText;

  static const Color mainStatsCardBackground = Color(0xFFFCC000); // Light green base
  static const Color mainStatsCardWave = Color(0xFF7ACC7A); // Darker green for wave if custom painted

  static const Color alarmCardStart = Color(0xFFA1C4FD); // Light blue
  static const Color alarmCardEnd = Color(0xFFC2E9FB);   // Lighter blue
  static const Color gradesCardStart = Color(0xFF84FAB0); // Light green
  static const Color gradesCardEnd = Color(0xFF8FD3F4);   // Light blue-green

  static const Color lineChartCardBackground = Color(0xFFF0E2D8); // Light brownish-pink
  static const Color calendarCardBackground = Colors.white; // Or a very light pattern
  static const Color calendarLabelBackground = authAccentRed;
}