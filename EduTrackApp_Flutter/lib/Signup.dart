// lib/sign_up_screen.dart
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:edutruck/Login.dart';      // Assuming Login.dart is your SignInScreen
import 'package:edutruck/utils/colors.dart'; // Assuming project name

// If Login.dart is actually SignInScreen.dart, the import should be:
// import 'package:edutruck/sign_in_screen.dart';


class SignUpScreen extends StatefulWidget {
  const SignUpScreen({super.key});

  @override
  State<SignUpScreen> createState() => _SignUpScreenState();
}

class _SignUpScreenState extends State<SignUpScreen> {
  final _formKey = GlobalKey<FormState>();
  final TextEditingController _fullNameController = TextEditingController();
  final TextEditingController _registerNumberController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final TextEditingController _confirmPasswordController = TextEditingController();

  bool _isPasswordVisible = false;
  bool _isConfirmPasswordVisible = false;

  @override
  void dispose() {
    _fullNameController.dispose();
    _registerNumberController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  void _togglePasswordVisibility() {
    setState(() {
      _isPasswordVisible = !_isPasswordVisible;
    });
  }

  void _toggleConfirmPasswordVisibility() {
    setState(() {
      _isConfirmPasswordVisible = !_isConfirmPasswordVisible;
    });
  }

  void _performSignUp() {
    if (_formKey.currentState!.validate()) {
      print('Full Name: ${_fullNameController.text}');
      print('Register Number: ${_registerNumberController.text}');
      print('Email: ${_emailController.text}');
      print('Password: ${_passwordController.text}');
      // TODO: Implement actual sign-up logic
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Creating Account...')),
      );
      // Example: Navigate to sign-in screen
      // Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const SignInScreen())); // Or your Login.dart
    }
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String label,
    required String hintText,
    bool isPassword = false,
    bool? isVisible,
    VoidCallback? toggleVisibility,
    TextInputType keyboardType = TextInputType.text,
    String? Function(String?)? validator,
    Widget? suffixIcon,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.bold,
            color: AppColors.authAccentRed, // <-- CORRECTED
          ),
        ),
        TextFormField(
          controller: controller,
          obscureText: isPassword && !(isVisible ?? false),
          keyboardType: keyboardType,
          decoration: InputDecoration(
            hintText: hintText,
            hintStyle: TextStyle(color: AppColors.hintText.withOpacity(0.7)),
            suffixIcon: suffixIcon ?? (isPassword
                ? IconButton(
              icon: Icon(
                (isVisible ?? false) ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                color: AppColors.iconColor, // <-- CORRECTED
              ),
              onPressed: toggleVisibility,
            )
                : null),
            enabledBorder: const UnderlineInputBorder(
              borderSide: BorderSide(color: AppColors.textFieldUnderline),
            ),
            focusedBorder: const UnderlineInputBorder(
              borderSide: BorderSide(color: AppColors.authAccentRed), // <-- CORRECTED
            ),
          ),
          validator: validator,
        ),
      ],
    );
  }


  @override
  Widget build(BuildContext context) {
    final screenHeight = MediaQuery.of(context).size.height;

    return Scaffold(
      backgroundColor: AppColors.authBackground, // <-- CORRECTED
      body: Stack(
        children: [
          Positioned(
            top: screenHeight * 0.08,
            left: 30,
            right: 30,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Expanded(
                  child: Text(
                    'Create Your\nAccount',
                    style: TextStyle(
                      fontSize: 38,
                      fontWeight: FontWeight.bold,
                      color: AppColors.white,
                      height: 1.2,
                    ),
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.more_vert, color: AppColors.white, size: 30),
                  onPressed: () {
                    print('Options menu tapped');
                  },
                ),
              ],
            ),
          ),
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: Container(
              height: screenHeight * 0.75,
              padding: const EdgeInsets.only(top: 25, left: 30, right: 30, bottom: 15),
              decoration: const BoxDecoration(
                color: AppColors.white,
                borderRadius: BorderRadius.only(
                  topLeft: Radius.circular(40.0),
                  topRight: Radius.circular(40.0),
                ),
              ),
              child: SingleChildScrollView(
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      _buildTextField(
                        controller: _fullNameController,
                        label: 'Full Name',
                        hintText: 'Enter your full name',
                        keyboardType: TextInputType.name,
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Please enter your full name';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 15),
                      _buildTextField(
                        controller: _registerNumberController,
                        label: 'Register Number',
                        hintText: 'Enter your register number (optional)',
                      ),
                      const SizedBox(height: 15),
                      _buildTextField(
                        controller: _emailController,
                        label: 'Gmail',
                        hintText: 'your.email@example.com',
                        keyboardType: TextInputType.emailAddress,
                        suffixIcon: const Icon(Icons.check, color: AppColors.iconColor), // <-- CORRECTED
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Please enter your email';
                          }
                          if (!RegExp(r"^[a-zA-Z0-9.a-zA-Z0-9.!#$%&'*+-/=?^_`{|}~]+@[a-zA-Z0-9]+\.[a-zA-Z]+").hasMatch(value)) {
                            return 'Please enter a valid email address';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 15),
                      _buildTextField(
                        controller: _passwordController,
                        label: 'Password',
                        hintText: 'Enter your password',
                        isPassword: true,
                        isVisible: _isPasswordVisible,
                        toggleVisibility: _togglePasswordVisibility,
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Please enter a password';
                          }
                          if (value.length < 6) {
                            return 'Password must be at least 6 characters';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 15),
                      _buildTextField(
                        controller: _confirmPasswordController,
                        label: 'Confirm Password',
                        hintText: 'Re-enter your password',
                        isPassword: true,
                        isVisible: _isConfirmPasswordVisible,
                        toggleVisibility: _toggleConfirmPasswordVisibility,
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Please confirm your password';
                          }
                          if (value != _passwordController.text) {
                            return 'Passwords do not match';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 30),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: _performSignUp,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.authAccentRed, // <-- CORRECTED
                            padding: const EdgeInsets.symmetric(vertical: 16),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(30.0),
                            ),
                            elevation: 5,
                          ),
                          child: const Text(
                            'Sign Up',
                            style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                                color: AppColors.white),
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),
                      Center(
                        child: RichText(
                          text: TextSpan(
                            text: "Do you have an Account? ",
                            style: const TextStyle(color: AppColors.secondaryText, fontSize: 15),
                            children: <TextSpan>[
                              TextSpan(
                                text: 'Login', // Changed from 'Sign up'
                                style: const TextStyle(
                                  color: AppColors.authAccentRed, // <-- CORRECTED
                                  fontWeight: FontWeight.bold,
                                  decoration: TextDecoration.underline,
                                ),
                                recognizer: TapGestureRecognizer()
                                  ..onTap = () {
                                    Navigator.pushReplacement(
                                      context,
                                      MaterialPageRoute(builder: (context) => const SignInScreen()), // Or your Login.dart
                                    );
                                  },
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}