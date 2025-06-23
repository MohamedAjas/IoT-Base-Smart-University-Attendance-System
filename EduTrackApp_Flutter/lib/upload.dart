import 'dart:io';
import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:dotted_border/dotted_border.dart';
import 'package:edutruck/utils/colors.dart';
import 'package:edutruck/result.dart'; // For ResultScreen and SubjectResult
import 'package:edutruck/home_screen.dart'; // For HomeScreen and SettingsScreen/HistoryScreen placeholders

class UploadScreen extends StatefulWidget {
  final String userRegisterNumber;

  const UploadScreen({super.key, required this.userRegisterNumber});

  @override
  State<UploadScreen> createState() => _UploadScreenState();
}

class _UploadScreenState extends State<UploadScreen> {
  PlatformFile? _selectedFile;
  String? _fileName;

  final List<Map<String, dynamic>> _recentFiles = [
    {'name': 'SEMESTER 1.1.csv', 'color1': const Color(0xFF4A56E2), 'color2': const Color(0xFFB066FE)},
    {'name': 'SEMESTER 1.2.csv', 'color1': const Color(0xFF20BF55), 'color2': const Color(0xFF01BAEF)},
  ];

  final int _thisScreenTabIndex = 2; // FAB is conceptually tab 2
  late int _currentIndex;

  @override
  void initState() {
    super.initState();
    _currentIndex = _thisScreenTabIndex; // Set initial active tab for UploadScreen
  }

  Future<void> _pickFile() async {
    try {
      FilePickerResult? result = await FilePicker.platform.pickFiles(
        type: FileType.any,
        allowCompression: false,
      );
      print('File picker result: $result');
      if (result != null && result.files.isNotEmpty) {
        setState(() {
          _selectedFile = result.files.single;
          _fileName = _selectedFile!.name;
          print('Selected file: ${_selectedFile!.name}');
        });
      } else {
        print('No file selected or result is empty.');
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('No file selected.')),
          );
        }
      }
    } catch (e) {
      print('Error picking file: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error picking file: $e')),
        );
      }
    }
  }

  void _clearFile() {
    setState(() {
      _selectedFile = null;
      _fileName = null;
    });
  }

  void _saveFile() {
    if (_selectedFile != null) {
      print('Saving file: ${_selectedFile!.name}');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Simulating save for: ${_selectedFile!.name}')),
      );
      // TODO: Implement actual file saving logic and CSV processing
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a file first.')),
      );
    }
  }

  void _onBottomNavItemTapped(int index) {
    if (index == _thisScreenTabIndex) {
      print('FAB (+) tapped on UploadScreen itself.');
      _pickFile();
      if (_currentIndex != _thisScreenTabIndex) {
        setState(() {
          _currentIndex = _thisScreenTabIndex;
        });
      }
      return;
    }

    Widget? nextPage;
    bool useCustomNavigation = false;

    switch (index) {
      case 0: // Home
        nextPage = HomeScreen(userRegisterNumber: widget.userRegisterNumber);
        break;
      case 1: // History
        nextPage = HistoryScreen(userRegisterNumber: widget.userRegisterNumber);
        break;
      case 3: // Learn/Result
        final List<SubjectResult> uploadContextResults = [];
        final String csvName = _fileName ?? "No file processed.csv";
        nextPage = ResultScreen(
          userRegisterNumber: widget.userRegisterNumber,
          currentCsvFileName: csvName,
          subjectData: uploadContextResults,
        );
        break;
      case 4: // Settings
        nextPage = SettingsScreen(userRegisterNumber: widget.userRegisterNumber);
        break;
    }

    if (nextPage != null) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => nextPage!),
      );
    } else if (!useCustomNavigation && index != _currentIndex) {
      setState(() {
        _currentIndex = index;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.authBackground,
      body: SafeArea(
        child: Column(
          children: [
            _buildHeader(),
            Expanded(
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.fromLTRB(20, 25, 20, 0),
                decoration: const BoxDecoration(
                  color: AppColors.white,
                  borderRadius: BorderRadius.only(
                    topLeft: Radius.circular(35.0),
                    topRight: Radius.circular(35.0),
                  ),
                ),
                child: SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildUploadSection(),
                      const SizedBox(height: 25),
                      _buildActionButtons(),
                      const SizedBox(height: 30),
                      const Text(
                        'Recent',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: AppColors.recentFilesText,
                        ),
                      ),
                      const SizedBox(height: 15),
                      _buildRecentFilesList(),
                      const SizedBox(height: 20),
                    ],
                  ),
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
      padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 20.0),
      child: Row(
        children: [
          const CircleAvatar(
            radius: 28,
            backgroundImage: AssetImage('images/img.png'),
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
          Container(
            decoration: BoxDecoration(
              color: AppColors.white.withOpacity(0.15),
              shape: BoxShape.circle,
            ),
            child: IconButton(
              icon: const Icon(Icons.notifications, color: AppColors.white, size: 26),
              onPressed: () { print('Notification icon tapped on UploadScreen'); },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildUploadSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Upload File',
          style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold, color: AppColors.primaryText),
        ),
        const Text('(.csv File)', style: TextStyle(fontSize: 14, color: AppColors.secondaryText)),
        const SizedBox(height: 20),
        GestureDetector(
          onTap: () {
            print('Tapped to pick file');
            _pickFile();
          },
          child: DottedBorder(
            // color: AppColors.uploadDropzoneBorder.withOpacity(0.6),
            // strokeWidth: 1.5,
            // dashPattern: const [7, 5],
            // borderType: BorderType.RRect,
            // radius: BorderRadius.circular(22),
            child: Container(
              height: 170,
              width: double.infinity,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    AppColors.uploadDropzoneBackgroundStart.withOpacity(0.3),
                    AppColors.uploadDropzoneBackgroundEnd.withOpacity(0.4),
                  ],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(22),
              ),
              child: Center(
                child: _selectedFile == null
                    ? Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.cloud_upload_outlined, size: 60, color: AppColors.iconColor.withOpacity(0.4)),
                    const SizedBox(height: 10),
                    Text(
                      'Tap to add file',
                      style: TextStyle(color: AppColors.secondaryText.withOpacity(0.7), fontSize: 15),
                    ),
                  ],
                )
                    : Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.insert_drive_file_rounded, size: 50, color: AppColors.authAccentRed),
                    const SizedBox(height: 12),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 20.0),
                      child: Text(
                        _fileName ?? 'No file selected',
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w500, color: AppColors.primaryText),
                        overflow: TextOverflow.ellipsis,
                        maxLines: 2,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildActionButtons() {
    return Row(
      children: [
        Expanded(
          child: ElevatedButton(
            onPressed: _saveFile,
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.uploadButtonSave,
              padding: const EdgeInsets.symmetric(vertical: 18),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12.0)),
            ),
            child: const Text('Save', style: TextStyle(fontSize: 17, color: AppColors.white, fontWeight: FontWeight.w600)),
          ),
        ),
        const SizedBox(width: 15),
        Expanded(
          child: ElevatedButton(
            onPressed: _clearFile,
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.uploadButtonClear,
              padding: const EdgeInsets.symmetric(vertical: 18),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12.0)),
            ),
            child: const Text('Clear', style: TextStyle(fontSize: 17, color: AppColors.white, fontWeight: FontWeight.w600)),
          ),
        ),
      ],
    );
  }

  Widget _buildRecentFilesList() {
    if (_recentFiles.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 20.0),
        child: Center(child: Text('No recent files.', style: TextStyle(color: AppColors.secondaryText))),
      );
    }
    return ListView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: _recentFiles.length,
      itemBuilder: (context, index) {
        final file = _recentFiles[index];
        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.symmetric(vertical: 22, horizontal: 20),
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [file['color1'] as Color, file['color2'] as Color],
              begin: Alignment.centerLeft,
              end: Alignment.centerRight,
            ),
            borderRadius: BorderRadius.circular(18),
            boxShadow: [
              BoxShadow(
                color: (file['color1'] as Color).withOpacity(0.25),
                blurRadius: 7,
                spreadRadius: 1,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          child: Center(
            child: Text(
              file['name'] as String,
              style: const TextStyle(color: AppColors.white, fontSize: 16, fontWeight: FontWeight.bold, letterSpacing: 0.5),
            ),
          ),
        );
      },
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