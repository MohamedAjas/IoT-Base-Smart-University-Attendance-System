// lib/main.dart
import 'package:edutruck/result.dart';
import 'package:flutter/material.dart';
import 'Welcome.dart';
import 'package:edutruck/upload.dart';
import 'package:edutruck/history.dart';
// import 'package:edutruck/result.dart'; // Duplicate import, can be removed
import 'package:edutruck/home_screen.dart';

const String sampleUserRegNo = "SEU/IS/19/ICT/090";
const String sampleCsvFile = "SEMESTER2.1.CSV";

final List<SubjectResult> sampleResults = [
  SubjectResult(code: 'CIS11022', name: 'Database Design', eligibilityStatus: 'Eligible'),
  SubjectResult(code: 'CIS11051', name: 'Practical for Database Design', eligibilityStatus: 'Not Eligible'),
  SubjectResult(code: 'SWT11021', name: 'Essentials of ICT and PC Applications', eligibilityStatus: 'Eligible'),
  SubjectResult(code: 'CMS11012', name: 'Mathematics for ICT', eligibilityStatus: 'Not Eligible'),
  SubjectResult(code: 'CMS11013', name: 'English-01', eligibilityStatus: 'Eligible'),
  SubjectResult(code: 'SWT11023', name: 'Practical for Essentials of ICT and PC Applications', eligibilityStatus: 'Eligible'),
  SubjectResult(code: 'CIS11013', name: 'Logic Designing and Computer Organization', eligibilityStatus: 'Eligible'),
  SubjectResult(code: 'SWT11023', name: 'Fundamentals of Programming', eligibilityStatus: 'Not Eligible'), // Note: SWT11023 is repeated, this is fine.
];

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'EduTrack',
      debugShowCheckedModeBanner: false, // Removes the debug banner
      theme: ThemeData(
        primarySwatch: Colors.red, // You can define a base color
        // You can customize more of your app's theme here
      ),
        // home: const WelcomeScreen(), // Set WelcomeScreen as the home
       //   home: const UploadScreen(userRegisterNumber: 'SEU/IS/19/ICT/090',), // Set LoginScreen as the hom
      //  home: ResultScreen( // Set ResultScreen as the home
      //   userRegisterNumber: sampleUserRegNo,
      //   currentCsvFileName: sampleCsvFile,
      //   subjectData: sampleResults, // sampleResults would need to be defined here
      // ),
       home: HomeScreen(userRegisterNumber: "SEU/IS/19/ICT/090"), // <--- THIS LINE DOES IT
      // home: HistoryUploadItem (fileName: 'sampleCsvFile',color1: Colors.blueAccent,color2: Colors.red),
    );
  }
}