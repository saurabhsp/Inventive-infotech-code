   <form method="get" class="toolbar" style="gap:10px;flex-wrap:wrap">
      <input type="hidden" name="plan_id" value="<?= (int)$plan_id_filter ?>">

      <input class="inp js-date-ddmmyyyy" type="text" name="created_from" value="<?= h($created_from_raw) ?>" placeholder="Reg Date From (dd-mm-yyyy)" autocomplete="off">
      <input class="inp js-date-ddmmyyyy" type="text" name="created_to" value="<?= h($created_to_raw) ?>" placeholder="Reg Date To (dd-mm-yyyy)" autocomplete="off">

      <input class="inp" type="text" name="q" value="<?= h($q) ?>" placeholder="Search name/mobile/referral/org..." style="min-width:240px">
      <input class="inp" type="text" name="city_id" value="<?= h($city_id) ?>" placeholder="City Name">


      <select class="inp" name="status_id" title="Status">
        <option value="1" <?= $status_id === 1 ? 'selected' : '' ?>>Active</option>
        <option value="0" <?= $status_id === 0 ? 'selected' : '' ?>>Inactive</option>
        <option value="-1" <?= $status_id === -1 ? 'selected' : '' ?>>Any</option>
      </select>

      <input class="inp" type="text" name="referral_code" value="<?= h($referral_code_in) ?>" placeholder="Referral Code (input)">


      <!-- Status filter -->
      <!-- KYC Status filter -->
      <select name="kyc_status_id" class="inp">
        <option value="">All KYC Status</option>
        <?php
        $kycStatuses = [];
        $rs = mysqli_query($con, "SELECT id,name FROM jos_app_kycstatus ORDER BY id");
        while ($r = mysqli_fetch_assoc($rs)) $kycStatuses[] = $r;

        $kyc_status_id = isset($_GET['kyc_status_id']) ? $_GET['kyc_status_id'] : '';
        ?>

        <?php foreach ($kycStatuses as $st): ?>
          <option value="<?= (int)$st['id'] ?>"
            <?= ($kyc_status_id !== '' && (int)$kyc_status_id === (int)$st['id']) ? 'selected' : '' ?>>
            <?= h($st['name']) ?>
          </option>
        <?php endforeach; ?>

        <option value="NOT_SUBMITTED"
          <?= ($kyc_status_id === 'NOT_SUBMITTED') ? 'selected' : '' ?>>
          Not Submitted (no docs)
        </option>
      </select>


      <!-- <select class="inp" name="plan_access" title="Plan Access">
        <option value="0" <?= $plan_access_in === 0 ? 'selected' : '' ?>>Plan Access: Any</option>
        <option value="1" <?= $plan_access_in === 1 ? 'selected' : '' ?>>Free</option>
        <option value="2" <?= $plan_access_in === 2 ? 'selected' : '' ?>>Premium</option>
      </select> -->

      <select class="inp" name="subscription_status">
        <option value="" <?= $subscription_status === '' ? 'selected' : '' ?>>Subscription: Any</option>
        <option value="active" <?= $subscription_status === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="expired" <?= $subscription_status === 'expired' ? 'selected' : '' ?>>Expired</option>
      </select>

      <select class="inp" name="image_filter">
        <option value="" <?= $image_filter === '' ? 'selected' : '' ?>>Image: All</option>
        <option value="available" <?= $image_filter === 'available' ? 'selected' : '' ?>>Image Available</option>
        <option value="not_available" <?= $image_filter === 'not_available' ? 'selected' : '' ?>>Image Not Available</option>
      </select>

      <select class="inp" name="sort">
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A–Z</option>
        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z–A</option>
        <option value="city_asc" <?= $sort === 'city_asc' ? 'selected' : '' ?>>City ↑</option>
        <option value="city_desc" <?= $sort === 'city_desc' ? 'selected' : '' ?>>City ↓</option>
      </select>

      <button class="btn primary" type="submit">Apply</button>

      <div style="flex:1"></div>
      <a class="btn secondary" href="<?= h(keep_params(['view' => 'last50', 'page' => 1])) ?>">Last 50</a>
      <a class="btn secondary" href="<?= h(keep_params(['view' => 'all', 'page' => 1])) ?>">View All</a>
    </form>