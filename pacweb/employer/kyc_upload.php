<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "../web_api/includes/db_config.php";

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

$user = $_SESSION['user'];
$userid = $user['id'];
$profile_id = $user['profile_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['docurl'])) {

  $api_url = API_BASE_URL . "addRecruterkyc.php";

  $recruiter_id = $profile_id;
  $kycdoctype_id = $_POST['kycdoctype_id'];
  $docno = $_POST['docno'];

  $tmpFile = $_FILES['docurl']['tmp_name'];
  $fileName = $_FILES['docurl']['name'];
  $fileType = $_FILES['docurl']['type'];

  $cfile = new CURLFile($tmpFile, $fileType, $fileName);

  $postData = [
    "recruiter_id" => $recruiter_id,
    "kycdoctype_id" => $kycdoctype_id,
    "docno" => $docno,
    "docurl" => $cfile
  ];

  $ch = curl_init();

  curl_setopt($ch, CURLOPT_URL, $api_url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

  $response = curl_exec($ch);

  curl_close($ch);

  $result = json_decode($response, true);

  if ($result['status'] == "success") {

    echo "<script>
        alert('Document Uploaded Successfully');
        window.location.href='my_profile.php';
        </script>";
    exit;
  } else {

    echo "<script>
        alert('Upload failed');
        </script>";
  }
}
?>











<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>KYC Status | Pacific iConnect</title>
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    rel="stylesheet" />
    <link rel="stylesheet" href="/style.css">
  <style>
    :root {
      /* Theme Colors matching Profile Page */
      --primary: #483ea8;
      --primary-light: #eceaf9;
      --primary-dark: #322b7a;
      --blue-btn: #2563eb;
      --success-green: #10b981;
      --success-bg: #d1fae5;
      --danger-red: #e53935;
      --text-dark: #1a1a1a;
      --text-muted: #555555;
      --border-light: #e5e7eb;
      --bg-body: #f4f6f9;
      --white: #ffffff;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
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

    a {
      text-decoration: none;
      transition: 0.3s;
      color: inherit;
    }

    button {
      cursor: pointer;
      outline: none;
    }

    /* --- 1. UNIFIED HEADER (From Profile Page) --- */
    header {
      background: var(--white);
      height: 70px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      position: sticky;
      top: 0;
      z-index: 1000;
      display: flex;
      align-items: center;
    }

    .header-container {
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .brand-group {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--primary);
      font-weight: 800;
      font-size: 1.3rem;
    }

    .location-pin {
      display: flex;
      align-items: center;
      gap: 5px;
      color: var(--primary);
      font-weight: 700;
      font-size: 1.1rem;
      cursor: pointer;
      padding: 5px 10px;
      border-radius: 8px;
      transition: 0.2s;
    }

    .location-pin:hover {
      background: var(--primary-light);
    }

    .location-pin i {
      color: var(--danger-red);
    }

    .desktop-nav {
      display: flex;
      gap: 20px;
      align-items: center;
    }

    .nav-link {
      font-weight: 600;
      color: #555;
      font-size: 1rem;
      padding: 5px 10px;
    }

    .nav-link:hover,
    .nav-link.active {
      color: var(--primary);
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .nav-action-icon {
      position: relative;
      cursor: pointer;
      font-size: 1.5rem;
      color: var(--primary);
      display: flex;
      align-items: center;
      transition: 0.2s;
    }

    .noti-badge {
      position: absolute;
      top: -5px;
      right: -8px;
      background: var(--danger-red);
      color: white;
      font-size: 0.65rem;
      font-weight: 800;
      padding: 2px 6px;
      border-radius: 10px;
      border: 2px solid white;
      line-height: 1.1;
    }

    .profile-dropdown-wrap {
      position: relative;
      padding-bottom: 10px;
      margin-bottom: -10px;
    }

    .user-profile {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 5px 15px 5px 5px;
      background: var(--primary-light);
      border-radius: 30px;
      cursor: pointer;
      transition: 0.2s;
    }

    .user-profile:hover {
      background: #e0dcf5;
    }

    .user-name {
      font-weight: 700;
      color: var(--primary);
      font-size: 0.95rem;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .user-avatar {
      width: 32px;
      height: 32px;
      background: var(--primary);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
    }

    .dropdown-menu {
      position: absolute;
      top: 100%;
      right: 0;
      background: white;
      min-width: 180px;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      border: 1px solid #eee;
      opacity: 0;
      visibility: hidden;
      transform: translateY(10px);
      transition: all 0.3s ease;
      z-index: 1000;
      padding: 10px 0;
    }

    .profile-dropdown-wrap:hover .dropdown-menu {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
    }

    .dropdown-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 20px;
      color: #555;
      font-weight: 600;
      font-size: 0.95rem;
      transition: 0.2s;
    }

    .dropdown-item:hover {
      background: #f8f9fa;
      color: var(--primary);
    }

    .text-danger {
      color: #d32f2f;
    }

    .text-danger:hover {
      color: #c62828;
      background: #ffebee;
    }

    /* --- 2. MOBILE HEADER & NAV --- */
    .bottom-nav {
      display: none;
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      background: white;
      height: 70px;
      border-top: 1px solid #eee;
      justify-content: space-around;
      align-items: center;
      z-index: 1000;
      padding-bottom: 5px;
      box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.03);
    }

    .nav-icon {
      display: flex;
      flex-direction: column;
      align-items: center;
      color: #888;
      font-size: 0.75rem;
      gap: 5px;
      font-weight: 600;
      text-decoration: none;
    }

    .nav-icon i {
      font-size: 1.3rem;
    }

    .nav-icon.active {
      color: var(--primary);
    }

    .nav-icon.active .icon-wrap {
      background: var(--primary-light);
      padding: 5px 15px;
      border-radius: 20px;
    }

    .mobile-header {
      display: none;
      align-items: center;
      justify-content: center;
      height: 60px;
      background: white;
      position: sticky;
      top: 0;
      z-index: 1000;
      border-bottom: 1px solid #eee;
    }

    .mobile-header-title {
      font-size: 1.2rem;
      font-weight: 700;
    }

    .mobile-back {
      position: absolute;
      left: 20px;
      font-size: 1.2rem;
      color: #333;
      cursor: pointer;
    }

    .mobile-user {
      position: absolute;
      right: 20px;
      font-size: 1.5rem;
      color: var(--primary);
      cursor: pointer;
    }

    /* --- 3. KYC PAGE CONTENT --- */
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 30px 20px 80px;
      flex: 1;
      width: 100%;
      display: flex;
      justify-content: center;
    }

    .kyc-container {
      background: var(--white);
      max-width: 800px;
      width: 100%;
      border-radius: 16px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
      border: 1px solid var(--border-light);
      padding: 40px 50px;
    }

    /* Desktop internal header (Hidden on mobile) */
    .desktop-card-header {
      display: flex;
      align-items: center;
      margin-bottom: 30px;
      padding-bottom: 15px;
      border-bottom: 1px solid var(--border-light);
    }

    .desktop-card-header .back-btn {
      background: none;
      border: none;
      font-size: 1.3rem;
      cursor: pointer;
      color: var(--text-dark);
      margin-right: 15px;
      transition: color 0.2s;
    }

    .desktop-card-header .back-btn:hover {
      color: var(--primary);
    }

    .desktop-card-header h2 {
      font-size: 1.3rem;
      font-weight: 800;
    }

    .status-info {
      text-align: center;
      margin-bottom: 35px;
    }

    .status-info h3 {
      font-size: 1.1rem;
      color: var(--text-muted);
      margin-bottom: 12px;
      font-weight: 600;
    }

    .status-info h2 {
      font-size: 1.15rem;
      font-weight: 800;
      margin-bottom: 16px;
      max-width: 550px;
      margin-left: auto;
      margin-right: auto;
      line-height: 1.4;
    }

    .badge-verified {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background-color: var(--success-bg);
      color: var(--success-green);
      padding: 6px 16px;
      border-radius: 20px;
      border: 1px solid var(--success-green);
      font-weight: 700;
      font-size: 0.95rem;
      margin-bottom: 20px;
    }

    .sub-text {
      font-weight: 700;
      margin-bottom: 8px;
      font-size: 1.05rem;
    }

    .note-text {
      font-size: 0.9rem;
      color: var(--text-muted);
    }

    .doc-list {
      display: grid;
      grid-template-columns: 1fr;
      /* Fixed to 1 column for dynamic DB loading */
      gap: 20px;
      margin-bottom: 40px;
    }

    .doc-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 16px;
      border: 1px solid var(--border-light);
      border-radius: 10px;
      background: var(--white);
      transition:
        border-color 0.2s,
        box-shadow 0.2s;
    }

    .doc-item:hover {
      border-color: var(--blue-btn);
      box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);
    }

    .doc-info {
      display: flex;
      align-items: center;
      gap: 15px;
      flex: 1;
      padding-right: 15px;
    }

    .doc-icon {
      color: var(--blue-btn);
      font-size: 1.5rem;
    }

    .doc-name {
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--text-dark);
      line-height: 1.3;
    }

    .btn-upload {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--white);
      border: 1px solid var(--border-light);
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--text-muted);
      cursor: pointer;
      transition: all 0.2s;
      white-space: nowrap;
    }

    .btn-upload i {
      color: var(--text-muted);
      font-size: 1.1rem;
    }

    .btn-upload:hover {
      background: #f8fafc;
      border-color: var(--text-muted);
      color: var(--text-dark);
    }

    .footer-action {
      text-align: center;
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid var(--border-light);
    }

    .help-text {
      font-size: 1rem;
      font-weight: 700;
      margin-bottom: 20px;
      color: var(--text-dark);
    }

    .btn-back-profile {
      background-color: var(--blue-btn);
      color: var(--white);
      border: none;
      padding: 14px 32px;
      border-radius: 10px;
      font-size: 1.05rem;
      font-weight: 700;
      cursor: pointer;
      transition:
        background 0.3s,
        transform 0.2s;
      width: 100%;
      max-width: 300px;
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    }

    .btn-back-profile:hover {
      background-color: #1d4ed8;
      transform: translateY(-2px);
    }

    /* --- 4. UPLOAD MODAL --- */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.6);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2000;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }

    .modal-overlay.active {
      opacity: 1;
      visibility: visible;
    }

    .modal-card {
      background: var(--white);
      width: 90%;
      max-width: 450px;
      border-radius: 16px;
      padding: 30px;
      position: relative;
      transform: translateY(20px);
      transition: transform 0.3s ease;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    }

    .modal-overlay.active .modal-card {
      transform: translateY(0);
    }

    .modal-close {
      position: absolute;
      top: 20px;
      right: 20px;
      background: none;
      border: none;
      font-size: 1.3rem;
      color: var(--text-muted);
      cursor: pointer;
      transition: color 0.2s;
    }

    .modal-close:hover {
      color: #e53935;
    }

    .modal-title {
      font-size: 1.25rem;
      margin-bottom: 20px;
      font-weight: 800;
      color: var(--text-dark);
    }

    .drop-zone {
      border: 2px dashed #cbd5e1;
      border-radius: 12px;
      padding: 40px 20px;
      text-align: center;
      background: #f8fafc;
      cursor: pointer;
      transition: all 0.3s;
      margin-bottom: 25px;
    }

    .drop-zone:hover {
      border-color: var(--blue-btn);
      background: #f0f7ff;
    }

    .drop-zone i {
      font-size: 2.5rem;
      color: var(--blue-btn);
      margin-bottom: 12px;
    }

    .drop-zone p {
      font-size: 0.95rem;
      color: var(--text-muted);
      margin-bottom: 15px;
      font-weight: 500;
    }

    .btn-select-file {
      background: var(--white);
      border: 1px solid #cbd5e1;
      padding: 8px 20px;
      border-radius: 6px;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      color: var(--text-dark);
      transition: 0.2s;
    }

    .btn-select-file:hover {
      border-color: var(--blue-btn);
      color: var(--blue-btn);
    }

    .btn-submit-doc {
      width: 100%;
      background: var(--blue-btn);
      color: var(--white);
      border: none;
      padding: 14px;
      border-radius: 10px;
      font-weight: 700;
      font-size: 1.05rem;
      cursor: pointer;
      transition: 0.2s;
    }

    .btn-submit-doc:hover {
      background: #1d4ed8;
    }

    /* --- 5. RESPONSIVE QUERIES --- */
    @media (min-width: 768px) {
      body {
        padding: 0;
      }

      .kyc-container {
        padding: 40px 50px;
      }

      /* Removed the 2-column grid rule from here to keep it 1 column */
    }

    @media (max-width: 900px) {
      header {
        display: none;
      }

      .mobile-header {
        display: flex;
      }

      .bottom-nav {
        display: flex;
      }

      .container {
        padding: 15px;
      }

      .kyc-container {
        padding: 20px;
        border-radius: 12px;
      }

      .desktop-card-header {
        display: none;
      }

      /* Hide internal header on mobile since we have .mobile-header */
    }
  </style>
</head>

<body>
    <?php include "includes/preloader.php"; ?>
    <?php include "includes/header.php"; ?>
  <div class="mobile-header">
    <i
      class="fas fa-arrow-left mobile-back"
      onclick="window.location.href = 'my_profile.php'"></i>
    <span class="mobile-header-title">KYC Status</span>
    <i class="fas fa-user-circle mobile-user"></i>
  </div>
  <div class="container">
    <div class="kyc-container">
      <div class="desktop-card-header">
        <button
          class="back-btn"
          onclick="window.location.href = 'my_profile.php'">
          <i class="fas fa-arrow-left"></i>
        </button>
        <h2>KYC Status</h2>
      </div>
      <div class="status-info">
        <h3>You're almost there!</h3>
        <h2>
          To boost trust and visibility, please upload any one company
          document and earn a KYC Verified Badge.
        </h2>
        <div class="badge-verified">
          <i class="fas fa-check-circle"></i> KYC Verified
        </div>
        <p class="sub-text">
          Earn trust and get noticed, verified posts receive more
          applications!
        </p>
        <p class="note-text">
          (Note: Upload a minimum of 2 documents to complete the verification
          process)
        </p>
      </div>
      <div class="doc-list">
        <div class="doc-item">
          <div class="doc-info">
            <i class="fas fa-file-alt doc-icon"></i>
            <span class="doc-name">Company/Proprietorship/Partnership Registration
              Certificate</span>
          </div>
          <button
            class="btn-upload"
            onclick="openModal('Registration Certificate',1)">
            <i class="fas fa-check-circle"></i> Upload
          </button>
        </div>
        <div class="doc-item">
          <div class="doc-info">
            <i class="fas fa-file-invoice doc-icon"></i>
            <span class="doc-name">GST Certificate</span>
          </div>
          <button class="btn-upload" onclick="openModal('GST Certificate',2)">
            <i class="fas fa-check-circle"></i> Upload
          </button>
        </div>
        <div class="doc-item">
          <div class="doc-info">
            <i class="fas fa-file-signature doc-icon"></i>
            <span class="doc-name">Udyam Aadhar</span>
          </div>
          <button class="btn-upload" onclick="openModal('Udyam Aadhar',3)">
            <i class="fas fa-check-circle"></i> Upload
          </button>
        </div>
        <div class="doc-item">
          <div class="doc-info">
            <i class="fas fa-id-card doc-icon"></i>
            <span class="doc-name">Pan Card</span>
          </div>
          <button class="btn-upload" onclick="openModal('Pan Card',4)">
            <i class="fas fa-check-circle"></i> Upload
          </button>
        </div>
        <div class="doc-item">
          <div class="doc-info">
            <i class="fas fa-file-contract doc-icon"></i>
            <span class="doc-name">TAN Certificate</span>
          </div>
          <button class="btn-upload" onclick="openModal('TAN Certificate',5)">
            <i class="fas fa-check-circle"></i> Upload
          </button>
        </div>
        <div class="doc-item">
          <div class="doc-info">
            <i class="fas fa-address-card doc-icon"></i>
            <span class="doc-name">Aadhar Card (Proprietor)</span>
          </div>
          <button
            class="btn-upload"
            onclick="openModal('Aadhar Card (Proprietor)',6)">
            <i class="fas fa-check-circle"></i> Upload
          </button>
        </div>
        <div class="doc-item">
          <div class="doc-info">
            <i class="fas fa-store doc-icon"></i>
            <span class="doc-name">Shop and Establishment Certificate</span>
          </div>
          <button
            class="btn-upload"
            onclick="openModal('Shop and Establishment Certificate',7)">
            <i class="fas fa-check-circle"></i> Upload
          </button>
        </div>
      </div>
      <div class="footer-action">
        <p class="help-text">Need help? Reach us at +917030933999</p>
        <button
          class="btn-back-profile"
          onclick="window.location.href = 'my_profile.php'">
          Back to Profile
        </button>
      </div>
    </div>
  </div>
  <div class="bottom-nav">
    <a href="#" class="nav-icon">
      <div class="icon-wrap"><i class="fas fa-home"></i></div>
      Home
    </a>
    <a href="#" class="nav-icon">
      <div class="icon-wrap"><i class="fas fa-file-alt"></i></div>
      Post Jobs
    </a>
    <a href="#" class="nav-icon">
      <div class="icon-wrap"><i class="fas fa-user-friends"></i></div>
      Applications
    </a>
    <a href="#" class="nav-icon active">
      <div class="icon-wrap"><i class="fas fa-user"></i></div>
      Profile
    </a>
  </div>
  <div class="modal-overlay" id="uploadModal">
    <div class="modal-card">

      <form id="kycForm" method="POST" enctype="multipart/form-data">

        <button type="button" class="modal-close" onclick="closeModal()">
          <i class="fas fa-times"></i>
        </button>

        <h3 class="modal-title" id="modalDocName">Upload Document</h3>

        <input type="hidden" name="kycdoctype_id" id="kycdoctype_id">
        <input type="hidden" name="recruiter_id" value="<?= $userid ?>">

        <div
          class="drop-zone"
          onclick="document.getElementById('fileInput').click()">
          <i class="fas fa-cloud-upload-alt"></i>
          <p>Drag and drop your file here, or click to browse</p>

          <button type="button" class="btn-select-file">Select File</button>

          <input
            type="file"
            name="docurl"
            id="fileInput"
            style="display:none"
            accept="image/png,image/jpeg,application/pdf" />
        </div>

        <div style="margin-bottom:15px">
          <input
            type="text"
            name="docno"
            placeholder="Enter Document Number"
            style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px">
        </div>

        <button type="button" class="btn-submit-doc" onclick="submitDocument()">
          Submit Document
        </button>

      </form>

    </div>
  </div>
  <?php include "includes/bottom-bar.php"; ?>

  <script>
    window.onload = () => document.getElementById("global-preloader")?.remove();
    const modal = document.getElementById("uploadModal");
    const modalTitle = document.getElementById("modalDocName");

    function openModal(docName, docId) {
      modalTitle.textContent = "Upload " + docName;
      document.getElementById("kycdoctype_id").value = docId;
      modal.classList.add("active");
    }

    function closeModal() {
      modal.classList.remove("active");
    }

    modal.addEventListener("click", function(e) {
      if (e.target === modal) {
        closeModal();
      }
    });

    function submitDocument() {
      const form = document.getElementById("kycForm");
      const fileInput = document.getElementById("fileInput");

      if (fileInput.files.length === 0) {
        alert("Please select a file");
        return;
      }
      form.submit();
    }
    document.getElementById("fileInput").addEventListener("change", function() {
      if (this.files.length > 0) {
        const dropZoneText = document.querySelector(".drop-zone p");
        dropZoneText.innerHTML = "<strong>Selected:</strong> " + this.files[0].name;
      }
    });
  </script>
</body>

</html>