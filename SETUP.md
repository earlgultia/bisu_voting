# BISU Voting System - Setup Instructions

## Quick Start

If candidates are not showing, follow these steps:

### Step 1: Initialize Database Tables
1. Open your browser and go to: `http://localhost/bisu_voting/init_db.php`
2. This will create all necessary database tables automatically
3. It will also create an admin account (credentials below)

### Step 2: Admin Login
- Email: `admin.ssg@bisu.edu.ph`
- Password: `admin123`

### Step 3: Add Candidates
1. Login with admin credentials
2. Go to Admin Panel
3. Add candidates with their position, name, and details
4. Optionally upload a photo for each candidate

### Step 4: Student Voting
1. Students register with their @bisu.edu.ph email
2. Login to see the ballot
3. Select a candidate for each position
4. Submit their vote

## Troubleshooting

**Candidates not showing after adding them:**
- Make sure you clicked "Add Candidate" and the success message appeared
- Refresh the student page
- Check the database using: `http://localhost/bisu_voting/debug.php`

**"Unable to load candidates" error:**
- Run the init_db.php script first
- Check that your MySQL server is running
- Verify the database connection in config/db.php

**"Leave this site?" warning when adding candidates:**
- This has been fixed - the form should now submit without warnings

## Database Info

- Database Name: `voting_db`
- Host: `localhost`
- User: `root`
- Password: (empty)

Tables:
- `students` - Stores student accounts
- `candidates` - Stores candidate information
- `votes` - Stores voting records
