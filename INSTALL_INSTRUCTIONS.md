# Manual Installation Instructions (Alternative Method)

Since the ZIP upload is having issues, you can install the plugin manually:

## Option 1: Direct File Copy (Recommended)

1. **Locate your Moodle installation directory** on the server (e.g., `/var/www/html/moodle` or `C:\xampp\htdocs\moodle`)

2. **Navigate to:** `{moodle-root}/report/`

3. **Copy the entire `report-plugin` folder contents** to create:
   ```
   {moodle-root}/report/questionbank/
   ```

4. **Ensure the structure is:**
   ```
   moodle/
   └── report/
       └── questionbank/
           ├── classes/
           ├── db/
           ├── lang/
           ├── index.php
           ├── version.php
           ├── styles.css
           └── README.md
   ```

5. **Login to Moodle as admin**

6. **Moodle will detect the new plugin** and prompt you to upgrade the database

7. **Click "Upgrade Moodle database now"**

## Option 2: Fix for ZIP Upload

The ZIP upload error might be due to Moodle's plugin validation. Try these steps:

1. In Moodle, go to: **Site administration → Development → Purge all caches**

2. Try uploading the ZIP again with **Plugin type: Course report** selected

3. If it still fails, use **Option 1** above (manual file copy)

## Verification

After installation, verify by:
- Going to: **Site administration → Plugins → Plugins overview**
- Search for "questionbank"
- You should see "Question Bank Report" listed under Reports
