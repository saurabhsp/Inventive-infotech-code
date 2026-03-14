<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Standard Job | Pacific iConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            /* Pacific iConnect Theme Colors */
            --primary: #483EA8;        
            --primary-light: #eceaf9;
            --primary-dark: #322b7a;
            --blue-btn: #2563eb;
            --blue-hover: #1d4ed8;
            --success-green: #10b981;
            --danger-red: #e53935;
            --text-dark: #1a1a1a;
            --text-muted: #64748b;
            --border-light: #cbd5e1;
            --bg-body: #f8fafc;        
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-dark);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        a { text-decoration: none; transition: 0.3s; color: inherit; }
        button { cursor: pointer; outline: none; border: none; font-family: inherit; }

        /* --- 1. UNIFIED DESKTOP HEADER --- */
        header {
            background: var(--white); height: 70px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky; top: 0; z-index: 1000;
            display: flex; align-items: center;
        }
        .header-container { 
            width: 100%; max-width: 1200px; margin: 0 auto; padding: 0 20px;
            display: flex; justify-content: space-between; align-items: center;
        }
        
        .brand-group { display: flex; align-items: center; gap: 20px; }
        .brand { display: flex; align-items: center; gap: 8px; color: var(--primary); font-weight: 800; font-size: 1.3rem; }

        .desktop-nav { display: flex; gap: 20px; align-items: center; }
        .nav-link { font-weight: 600; color: #555; font-size: 1rem; padding: 5px 10px;}
        .nav-link:hover, .nav-link.active { color: var(--primary); }

        .header-actions { display: flex; align-items: center; gap: 15px; }
        .nav-action-icon {
            position: relative; cursor: pointer; font-size: 1.5rem; color: var(--primary);
            display: flex; align-items: center; transition: 0.2s;
        }
        .noti-badge {
            position: absolute; top: -5px; right: -8px;
            background: var(--danger-red); color: white; font-size: 0.65rem; font-weight: 800;
            padding: 2px 6px; border-radius: 10px; border: 2px solid white; line-height: 1.1;
        }

        .user-profile { 
            display: flex; align-items: center; gap: 8px; 
            padding: 5px 15px 5px 5px; 
            background: var(--primary-light); border-radius: 30px; 
            cursor: pointer; transition: 0.2s;
        }
        .user-profile:hover { background: #e0dcf5; }
        .user-name { font-weight: 700; color: var(--primary); font-size: 0.95rem; display: flex; align-items: center; gap: 5px; }
        .user-avatar { 
            width: 32px; height: 32px; background: var(--primary); color: white; 
            border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700;
        }

        /* --- 2. MAIN CONTENT AREA (FORM) --- */
        .main-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 25px 20px 60px; 
        }

        .form-card {
            background: var(--white);
            width: 100%;
            max-width: 1200px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            padding: 25px 40px 30px; 
        }

        .desktop-page-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            color: var(--text-dark);
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        /* Grid Layout for Desktop (Horizontal) */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 40px; 
            align-items: start;
        }

        .form-group {
            margin-bottom: 0; 
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .form-label {
            display: block;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        /* Used for 'From' / 'To' dropdowns within a single grid cell */
        .form-row {
            display: flex;
            gap: 15px; 
        }
        .form-col {
            flex: 1;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px; 
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-size: 0.95rem;
            color: var(--text-dark);
            background-color: var(--white);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .form-control:focus {
            border-color: var(--blue-btn);
            box-shadow: 0 0 0 3px #eff6ff;
        }
        
        select.form-control {
            appearance: auto;
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 95px; 
            font-family: inherit;
            line-height: 1.5;
            flex: 1; 
        }

        /* Toggle Buttons */
        .toggle-container {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap; /* Allows wrapping on very small screens */
        }
        .btn-toggle {
            padding: 8px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            background: #f8fafc;
            color: var(--text-muted);
            border: 1px solid var(--border-light);
            transition: all 0.2s;
            flex: 1;
            text-align: center;
            white-space: nowrap;
        }
        .btn-toggle.active {
            background: var(--blue-btn);
            color: var(--white);
            border-color: var(--blue-btn);
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.2);
        }

        .deadline-date-wrapper {
            display: none; 
            margin-top: 5px;
            animation: fadeIn 0.3s ease;
        }
        .deadline-date-wrapper.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Submit Button Container */
        .submit-container {
            text-align: center;
            margin-top: 25px;
        }

        .btn-submit {
            width: auto;
            min-width: 300px;
            background: var(--blue-btn);
            color: var(--white);
            padding: 12px 30px;
            border-radius: 30px;
            font-size: 1.05rem;
            font-weight: 700;
            transition: background 0.2s;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            display: inline-block;
        }
        .btn-submit:hover {
            background: var(--blue-hover);
        }


        /* --- 3. MOBILE HEADER & NAV --- */
        .bottom-nav {
            display: none; position: fixed; bottom: 0; left: 0; width: 100%;
            background: white; height: 70px; border-top: 1px solid #eee;
            justify-content: space-around; align-items: center; z-index: 1000;
            padding-bottom: 5px; box-shadow: 0 -2px 10px rgba(0,0,0,0.03);
        }
        .nav-icon { 
            display: flex; flex-direction: column; align-items: center; 
            color: #888; font-size: 0.75rem; gap: 5px; font-weight: 600; text-decoration: none;
        }
        .nav-icon i { font-size: 1.3rem; }
        .nav-icon.active { color: var(--primary); }
        .nav-icon.active .icon-wrap { background: var(--primary-light); padding: 5px 15px; border-radius: 20px; }

        .mobile-header { display: none; align-items: center; justify-content: center; height: 60px; background: white; position: sticky; top: 0; z-index: 1000; border-bottom: 1px solid #eee; }
        .mobile-header-title { font-size: 1.2rem; font-weight: 700; }
        .mobile-back { position: absolute; left: 20px; font-size: 1.2rem; color: #333; cursor: pointer; }

        /* --- 4. RESPONSIVE SETTINGS (Mobile Vertical Layout) --- */
        @media (max-width: 900px) {
            header { display: none; } 
            .mobile-header { display: flex; } 
            .bottom-nav { display: flex; }
            body { padding-bottom: 80px; background: var(--white); }
            
            .main-wrapper { padding: 20px 15px; }
            .desktop-page-title { display: none; } 
            
            .form-card {
                padding: 0; 
                border: none;
                box-shadow: none;
            }
            
            /* Switch to vertical layout on mobile */
            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .submit-container {
                margin-top: 15px;
            }

            .btn-submit { 
                width: 100%; 
            } 
        }
    </style>
</head>
<body>

    <header>
        <div class="header-container">
            <div class="brand-group">
                <div class="brand">
                    <i class="fas fa-user-tie"></i> <span>PACIFIC iCONNECT</span>
                </div>
            </div>
            
            <nav class="desktop-nav">
                <a href="#" class="nav-link">Find Jobs</a>
                <a href="#" class="nav-link">Companies</a>
                <a href="#" class="nav-link">For Employers</a>
            </nav>
            
            <div class="header-actions">
                <div class="nav-action-icon" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="noti-badge">3</span>
                </div>
                <div class="user-profile">
                    <div class="user-avatar">A</div>
                    <span class="user-name">Ashwin Jawale <i class="fas fa-chevron-down" style="font-size:0.75rem;"></i></span>
                </div>
            </div>
        </div>
    </header>

    <div class="mobile-header">
        <i class="fas fa-arrow-left mobile-back"></i>
        <span class="mobile-header-title">Post Standard Job</span>
    </div>

    <main class="main-wrapper">
        <div class="form-card">
            <h1 class="desktop-page-title">Post Standard Job</h1>

            <form action="#" method="POST">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Company Name</label>
                        <input type="text" class="form-control" value="INVENTIVE INFOTECH">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Job Position</label>
                        <select class="form-control">
                            <option>Try Software Developer</option>
                            <option>Frontend Developer</option>
                            <option>Backend Developer</option>
                            <option>UI/UX Designer</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">District/ Tehsil/ City</label>
                        <input type="text" class="form-control" placeholder="Choose District/ Taluka/ City">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Area/ Locality/ Village</label>
                        <input type="text" class="form-control" placeholder="Choose Area/ Locality/ Village">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <div class="toggle-container" id="genderToggleGroup">
                            <button type="button" class="btn-toggle" onclick="selectGender(this)">Male</button>
                            <button type="button" class="btn-toggle" onclick="selectGender(this)">Female</button>
                            <button type="button" class="btn-toggle active" onclick="selectGender(this)">Male / Female</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Basic Qualification</label>
                        <select class="form-control">
                            <option>Basic Qualification</option>
                            <option>10th Pass</option>
                            <option>12th Pass</option>
                            <option>Graduate</option>
                            <option>Post Graduate</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Experience</label>
                        <div class="form-row">
                            <div class="form-col">
                                <select class="form-control">
                                    <option>From</option>
                                    <option>0 Years (Fresher)</option>
                                    <option>1 Year</option>
                                    <option>2 Years</option>
                                </select>
                            </div>
                            <div class="form-col">
                                <select class="form-control">
                                    <option>To</option>
                                    <option>1 Year</option>
                                    <option>2 Years</option>
                                    <option>3+ Years</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Monthly Salary Range</label>
                        <div class="form-row">
                            <div class="form-col">
                                <select class="form-control">
                                    <option>From ₹</option>
                                    <option>₹10,000</option>
                                    <option>₹15,000</option>
                                    <option>₹20,000</option>
                                </select>
                            </div>
                            <div class="form-col">
                                <select class="form-control">
                                    <option>To ₹</option>
                                    <option>₹15,000</option>
                                    <option>₹20,000</option>
                                    <option>₹30,000</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Person for Interview :</label>
                        <input type="text" class="form-control" value="Suhel" placeholder="Enter name">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Interviewer/ HR Contact No</label>
                        <input type="tel" class="form-control" value="9823489786" placeholder="Enter contact number">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Interview Location</label>
                        <textarea class="form-control">A PANTACHA GOT RAVIVAR PETH, 82, Khalcha Rasta</textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Does this Job have a deadline?</label>
                        <div class="toggle-container">
                            <button type="button" class="btn-toggle active" id="btnYes" onclick="toggleDeadline(true)" style="flex: 0 0 auto; width: 100px;">Yes</button>
                            <button type="button" class="btn-toggle" id="btnNo" onclick="toggleDeadline(false)" style="flex: 0 0 auto; width: 100px;">No</button>
                        </div>
                        
                        <div class="deadline-date-wrapper active" id="deadlineDateGroup">
                            <input type="date" class="form-control" style="max-width: 100%;" placeholder="Select Date">
                        </div>
                    </div>
                </div>

                <div class="submit-container">
                    <button type="submit" class="btn-submit">Submit</button>
                </div>

            </form>
        </div>
    </main>

    <div class="bottom-nav">
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-home"></i></div>
            Home
        </a>
        <a href="#" class="nav-icon active">
            <div class="icon-wrap"><i class="fas fa-plus-square"></i></div>
            Post Jobs
        </a>
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-file-alt"></i></div>
            Applications
        </a>
        <a href="#" class="nav-icon">
            <div class="icon-wrap"><i class="fas fa-user"></i></div>
            Profile
        </a>
    </div>

    <script>
        // Logic for Yes/No Deadline toggle
        function toggleDeadline(isYes) {
            const btnYes = document.getElementById('btnYes');
            const btnNo = document.getElementById('btnNo');
            const dateGroup = document.getElementById('deadlineDateGroup');

            if (isYes) {
                btnYes.classList.add('active');
                btnNo.classList.remove('active');
                dateGroup.classList.add('active');
            } else {
                btnNo.classList.add('active');
                btnYes.classList.remove('active');
                dateGroup.classList.remove('active');
            }
        }

        // Logic for Gender selection buttons
        function selectGender(clickedButton) {
            // Get all buttons inside the gender toggle group
            const buttons = document.querySelectorAll('#genderToggleGroup .btn-toggle');
            // Remove active class from all
            buttons.forEach(btn => btn.classList.remove('active'));
            // Add active class to the clicked button
            clickedButton.classList.add('active');
        }
    </script>
</body>
</html>