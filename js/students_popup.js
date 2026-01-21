$(document).ready(function () {

    let activeStudentRow = null;

    $(document).on('click', '.student-row', function () {

        activeStudentRow = $(this);

        $('.student-row').removeClass('active');
        $(this).addClass('active');


        $('#m_roll').text($(this).data('roll'));
        $('#m_name').text($(this).data('name'));
        $('#m_gender').text($(this).data('gender'));
        $('#m_card').text($(this).data('card') || '—');
        $('#m_department').text($(this).data('department'));
        $('#m_batch').text($(this).data('batch'));
        $('#m_level_term').text(
            'Level ' + $(this).data('level') + ' / Term ' + $(this).data('term')
        );
        $('#m_date').text($(this).data('date'));

        var studentId = $(this).attr('data-id');

        // load courses
        $('#course_table').html('<tr><td colspan="2">Loading...</td></tr>');

        $('#course_table').load(
            'get_student_courses.php?student_id=' + studentId,
            function () {
                $('#studentModal').modal('show');
            }
        );

    });

    // ===============================
    // EDIT STUDENT (REDIRECT MODE)
    // ===============================
    $('#editStudent').on('click', function () {

        const row = $('.student-row.active');

        if (!row.length) {
            alert('No student selected');
            return;
        }

        const studentId = row.data('id');

        // Redirect to ManageUsers.php with student_id
        window.location.href = 'ManageUsers.php?student_id=' + studentId;
    });




    // ===============================
    // DELETE STUDENT
    // ===============================
    $('#deleteStudent').on('click', function () {

        if (!activeStudentRow) {
            alert('No student selected');
            return;
        }

        // Show confirmation modal
        $('#deleteConfirmModal').modal('show');
    });


    // ===============================
    // CONFIRM DELETE ACTION
    // ===============================
    $('#confirmDeleteStudent').on('click', function () {

        const student_id = $('.student-row.active').data('id');

        if (!student_id) {
            alert('Invalid student');
            return;
        }

        $.ajax({
            url: 'delete_student.php',
            type: 'POST',
            data: { student_id: student_id },
            success: function (res) {

                if (res === 'success') {

                    $('#deleteConfirmModal').modal('hide');
                    $('#studentModal').modal('hide');

                    alert('Student deleted successfully');
                    location.reload();

                } else {
                    alert(res);
                }
            }
        });
    });

});
