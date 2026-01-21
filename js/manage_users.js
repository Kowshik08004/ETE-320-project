$(document).ready(function () {

  console.log("manage_users.js running");

  // ===============================
  // SELECT RFID CARD (CRITICAL FIX)
  // ===============================
  $(document).on('click', '.select_btn', function (e) {
    e.preventDefault();

    const card_uid = $(this).data('card');

    if (!card_uid) {
      alert("Card UID missing");
      return;
    }

    // Highlight row
    $('.select_btn').closest('tr').css('background', '');
    $(this).closest('tr').css('background', '#70c276');

    // Fill input
    $('#card_uid').val(card_uid);

    // Clear student fields
    $('#student_id').val('');
    $('#name').val('');
    $('#number').val('');

    $('.alert_user')
      .html('<p class="alert alert-success">Card selected. Now fill student info.</p>')
      .fadeIn();

    setTimeout(() => $('.alert').fadeOut(), 3000);
  });


  // ===============================
  // REGISTER / UPDATE STUDENT
  // ===============================
  $(document).on('click', '.user_add', function () {

    const student_id = $('#student_id').val();
    const card_uid = $('#card_uid').val();
    const name = $('#name').val();
    const roll_no = $('#number').val();
    const department_id = $('#department_id').val();
    const batch = $('#batch').val();
    const level = $('#level').val();
    const term = $('#term').val();
    const gender = $("input[name='gender']:checked").val();

    if (!card_uid || !name || !roll_no || !department_id || !batch || !level || !term) {
      $('.alert_user')
        .html('<p class="alert alert-danger">Fill all required fields</p>')
        .fadeIn();
      return;
    }

    $.ajax({
      url: 'register_student.php',
      type: 'POST',
      data: {
        student_id,
        card_uid,
        name,
        roll_no,
        gender,
        department_id,
        batch,
        level,
        term
      },
      success: function (res) {

        if (res === 'success') {

          $('.alert_user')
            .html('<p class="alert alert-success">Student saved successfully</p>')
            .fadeIn();

          // reset form
          $('#student_id').val('');
          $('#card_uid').val('');
          $('#name').val('');
          $('#number').val('');

          // reload card list
          $('#manage_users').load('manage_users_up.php');

        } else {
          $('.alert_user')
            .html('<p class="alert alert-danger">' + res + '</p>')
            .fadeIn();
        }

        setTimeout(() => $('.alert').fadeOut(), 4000);
      }
    });
  });

});
