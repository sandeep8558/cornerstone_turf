import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutter_image_compress/flutter_image_compress.dart';
import '../controllers/auth_controller.dart';
import '../utils/api_constants.dart';
import '../utils/app_colors.dart';
import 'app_drawer.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final AuthController _authController = Get.find<AuthController>();
  bool _isEditing = false;
  
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _mobileController = TextEditingController();

  XFile? _imageFile;
  Uint8List? _imageBytes;
  final ImagePicker _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    _nameController.text = _authController.user['name'] ?? '';
    _mobileController.text = _authController.user['mobile_number'] ?? '';
  }

  @override
  void dispose() {
    _nameController.dispose();
    _mobileController.dispose();
    super.dispose();
  }

  Future<void> _pickImage(ImageSource source) async {
    final XFile? pickedFile = await _picker.pickImage(source: source);
    if (pickedFile != null) {
      // For cross-platform compatibility (especially Web), we work with bytes
      var bytes = await pickedFile.readAsBytes();

      // Compress the image bytes
      var compressedBytes = await FlutterImageCompress.compressWithList(
        bytes,
        quality: 70, // Adjust quality as needed
      );

      setState(() {
        _imageFile = pickedFile;
        _imageBytes = compressedBytes;
      });
    }
  }

  void _showPicker(BuildContext context) {
    showModalBottomSheet(
        context: context,
        backgroundColor: Colors.white,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          side: BorderSide(color: AppColors.green200),
        ),
        builder: (BuildContext bc) {
          return SafeArea(
            child: Wrap(
              children: <Widget>[
                ListTile(
                    leading: const Icon(Icons.photo_library, color: AppColors.primary),
                    title: const Text('Photo Library', style: TextStyle(color: AppColors.textMain, fontWeight: FontWeight.bold)),
                    onTap: () {
                      _pickImage(ImageSource.gallery);
                      Navigator.of(context).pop();
                    }),
                ListTile(
                  leading: const Icon(Icons.photo_camera, color: AppColors.primary),
                  title: const Text('Camera', style: TextStyle(color: AppColors.textMain, fontWeight: FontWeight.bold)),
                  onTap: () {
                    _pickImage(ImageSource.camera);
                    Navigator.of(context).pop();
                  },
                ),
              ],
            ),
          );
        });
  }

  @override
  Widget build(BuildContext context) {
    String? profilePhoto = _authController.user['profile_photo'];
    String? fullPhotoUrl = profilePhoto != null ? '${ApiConstants.imageBaseUrl}/storage/$profilePhoto' : null;

    return Scaffold(
      backgroundColor: AppColors.background,
      drawer: const AppDrawer(currentRoute: 'profile'),
      appBar: AppBar(
        title: const Text('My Profile', style: TextStyle(color: AppColors.textMain, fontWeight: FontWeight.bold)),
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Builder(
          builder: (context) => IconButton(
            icon: const Icon(Icons.menu, color: AppColors.textMain),
            onPressed: () => Scaffold.of(context).openDrawer(),
          ),
        ),
        actions: [
          IconButton(
            icon: Icon(_isEditing ? Icons.close : Icons.edit, color: AppColors.textMain),
            onPressed: () {
              setState(() {
                if (_isEditing) {
                  // Revert changes if cancelling
                  _nameController.text = _authController.user['name'] ?? '';
                  _mobileController.text = _authController.user['mobile_number'] ?? '';
                  _imageFile = null;
                  _imageBytes = null;
                }
                _isEditing = !_isEditing;
              });
            },
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20.0),
        child: Column(
          children: [
            Center(
              child: Stack(
                children: [
                  CircleAvatar(
                    radius: 50,
                    backgroundColor: AppColors.green100,
                    backgroundImage: _imageBytes != null
                        ? MemoryImage(_imageBytes!) as ImageProvider
                        : (fullPhotoUrl != null ? NetworkImage(fullPhotoUrl) : null),
                    child: _imageBytes == null && fullPhotoUrl == null
                        ? Icon(Icons.person, size: 60, color: AppColors.primary.withOpacity(0.3))
                        : null,
                  ),
                  if (_isEditing)
                    Positioned(
                      bottom: 0,
                      right: 0,
                      child: GestureDetector(
                        onTap: () => _showPicker(context),
                        child: Container(
                          padding: const EdgeInsets.all(8),
                          decoration: const BoxDecoration(
                            color: AppColors.primary,
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(Icons.camera_alt, size: 18, color: Colors.white),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 30),
            
            _buildProfileField(
              label: 'Full Name',
              controller: _nameController,
              isEditable: _isEditing,
              icon: Icons.person_outline,
            ),
            
            _buildProfileField(
              label: 'Email Address',
              controller: TextEditingController(text: _authController.user['email'] ?? 'N/A'),
              isEditable: false, // Email remains non-editable for now
              icon: Icons.email_outlined,
            ),
            
            _buildProfileField(
              label: 'Mobile Number',
              controller: _mobileController,
              isEditable: _isEditing,
              icon: Icons.phone_android_outlined,
            ),
            
            if (_isEditing) ...[
              const SizedBox(height: 40),
              Obx(() => SizedBox(
                width: double.infinity,
                height: 55,
                child: ElevatedButton(
                  onPressed: _authController.isLoading.value 
                    ? null 
                    : () async {
                        bool success = await _authController.updateProfile(
                          _nameController.text,
                          _mobileController.text,
                          fileBytes: _imageBytes,
                          fileName: _imageFile?.name,
                        );
                        if (success) {
                          setState(() => _isEditing = false);
                        }
                      },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
                  ),
                  child: _authController.isLoading.value
                    ? const CircularProgressIndicator(color: Colors.white)
                    : const Text('Save Changes', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Colors.white)),
                ),
              )),
            ],
            if (!_isEditing) ...[
              const SizedBox(height: 20),
              TextButton(
                onPressed: () {
                  showDialog(
                    context: context,
                    builder: (context) => AlertDialog(
                      backgroundColor: AppColors.cardBg,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(20),
                        side: const BorderSide(color: AppColors.green200),
                      ),
                      title: const Text('Delete Account', style: TextStyle(color: AppColors.textMain, fontWeight: FontWeight.bold)),
                      content: const Text(
                        'Are you sure you want to delete your account? This action cannot be undone and all your data will be permanently removed.',
                        style: TextStyle(color: AppColors.textSecondary),
                      ),
                      actions: [
                        TextButton(
                          onPressed: () => Navigator.pop(context),
                          child: const Text('Cancel', style: TextStyle(color: AppColors.textSecondary, fontWeight: FontWeight.bold)),
                        ),
                        TextButton(
                          onPressed: () {
                            Navigator.pop(context);
                            _authController.deleteAccount();
                          },
                          child: const Text('Delete', style: TextStyle(color: Colors.redAccent, fontWeight: FontWeight.bold)),
                        ),
                      ],
                    ),
                  );
                },
                child: const Text(
                  'Delete Account',
                  style: TextStyle(color: Colors.redAccent, fontSize: 16, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildProfileField({
    required String label,
    required TextEditingController controller,
    required bool isEditable,
    required IconData icon,
  }) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(left: 4, bottom: 8),
            child: Text(label, style: const TextStyle(color: AppColors.textSecondary, fontSize: 12, fontWeight: FontWeight.bold)),
          ),
          TextFormField(
            controller: controller,
            enabled: isEditable,
            style: const TextStyle(color: AppColors.textMain, fontSize: 16),
            decoration: InputDecoration(
              prefixIcon: Icon(icon, color: AppColors.primary, size: 20),
              filled: true,
              fillColor: isEditable ? Colors.white : AppColors.green50,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(15),
                borderSide: const BorderSide(color: AppColors.green200),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(15),
                borderSide: const BorderSide(color: AppColors.green200),
              ),
              disabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(15),
                borderSide: BorderSide.none,
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(15),
                borderSide: const BorderSide(color: AppColors.primary, width: 1.5),
              ),
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
            ),
          ),
        ],
      ),
    );
  }
}
