<!-- MOBILE BOTTOM NAV -->
<div class="bottom-nav">
    
    <a href="index.php" class="nav-icon <?php if($active=='home') echo 'active'; ?>">
        <div class="icon-wrap"><i class="fas fa-home"></i></div>
        Home
    </a>

    <a href="post-job.php" class="nav-icon <?php if($active=='post') echo 'active'; ?>">
        <div class="icon-wrap"><i class="fas fa-plus-square"></i></div>
        Post Jobs
    </a>

    <a href="applications.php" class="nav-icon <?php if($active=='applications') echo 'active'; ?>">
        <div class="icon-wrap"><i class="fas fa-file-alt"></i></div>
        Applications
    </a>

    <a href="my_profile.php" class="nav-icon <?php if($active=='profile') echo 'active'; ?>">
        <div class="icon-wrap"><i class="fas fa-user"></i></div>
        Profile
    </a>

</div>

<style>
/* === ISOLATED MOBILE BOTTOM NAV === */
.bottom-nav {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    background: #fff;
    height: 70px;
    border-top: 1px solid #eee;
    justify-content: space-around;
    align-items: center;
    z-index: 9999;
    padding-bottom: 5px;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
}

.bottom-nav .nav-icon {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #888;
    font-size: 0.75rem;
    gap: 5px;
    font-weight: 600;
    text-decoration: none;
}

.bottom-nav .nav-icon i {
    font-size: 1.3rem;
}

.bottom-nav .nav-icon.active {
    color: #483EA8;
}

.bottom-nav .nav-icon.active .icon-wrap {
    background: #eceaf9;
    padding: 5px 15px;
    border-radius: 20px;
}

/* MOBILE ONLY */
@media (max-width: 900px) {
    .bottom-nav {
        display: flex;
    }

    body {
        padding-bottom: 80px;
    }
}
</style>