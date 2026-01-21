<div class="unreg-cards-list">
  <div class="unreg-cards-title">Unregistered RFID Cards</div>
  <div class="unreg-cards-container">
    <?php
    require 'connectDB.php';
    $sql = "
      SELECT card_uid, status
      FROM rfid_cards
      WHERE student_id IS NULL
        AND status = 'active'
      ORDER BY card_uid DESC
    ";
    $res = mysqli_query($conn, $sql);
    if ($res && mysqli_num_rows($res) > 0) {
      while ($row = mysqli_fetch_assoc($res)) {
    ?>
        <div class="unreg-card-item">
          <div class="unreg-card-uid">
            <button
              type="button"
              class="select_btn"
              data-card="<?php echo htmlspecialchars($row['card_uid']); ?>">
              <?php echo htmlspecialchars($row['card_uid']); ?>
            </button>
          </div>
          <div class="unreg-card-status">Unregistered</div>
        </div>
    <?php
      }
    } else {
      echo "<div class='unreg-card-none'>No unregistered cards</div>";
    }
    ?>
  </div>
</div>
