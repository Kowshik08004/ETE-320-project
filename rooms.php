<?php
require 'admin_guard.php';
$flash = read_flash();

$rooms = [];
$q = $conn->query("SELECT room_id, room_name FROM class_rooms ORDER BY room_id DESC");
if ($q)
    while ($r = $q->fetch_assoc())
        $rooms[] = $r;
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Rooms</title>
    <link rel="stylesheet" href="css/manageusers.css">
    <style>
        /* ONLY small tweaks — does NOT replace your base UI */
        .container-rooms {
            max-width: 1050px;
            margin: 0 auto;
            padding: 18px 16px
        }

        .panel {
            background: #fff;
            border-radius: 10px;
            border: 1px solid rgba(0, 0, 0, .08);
            overflow: hidden
        }

        .panel-h {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(0, 0, 0, .08);
            font-weight: 800
        }

        .panel-b {
            padding: 16px
        }

        .msg {
            padding: 10px 12px;
            border-radius: 10px;
            margin: 10px 0 14px;
            border: 1px solid transparent
        }

        .success {
            background: #ecfdf5;
            color: #065f46;
            border-color: #a7f3d0
        }

        .danger {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fecaca
        }

        .info {
            background: #eff6ff;
            color: #1e40af;
            border-color: #bfdbfe
        }

        .form-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end
        }

        .form-row .field {
            flex: 1;
            min-width: 240px
        }

        .form-row label {
            display: block;
            font-weight: 800;
            font-size: 13px;
            margin-bottom: 6px
        }

        .form-row input {
            width: 100%;
            height: 44px;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            outline: none
        }

        .form-row input:focus {
            border-color: #7dd3fc;
            box-shadow: 0 0 0 4px rgba(56, 189, 248, .20)
        }

        .form-row button {
            height: 44px;
            padding: 0 16px;
            border: 0;
            border-radius: 10px;
            background: #0ea5a4;
            color: #fff;
            font-weight: 900;
            cursor: pointer
        }

        .table-wrap {
            margin-top: 14px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        thead th {
            background: #f1f5f9;
            color: #000;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            padding: 10px 12px;
            text-align: left
        }

        tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb
        }

        tbody tr:nth-child(even) td {
            background: #f8fafc
        }

        .btn-del {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 8px;
            background: #dc2626;
            color: #fff;
            text-decoration: none;
            font-weight: 900
        }

        .btn-del:hover {
            filter: brightness(.95)
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container-rooms">
        <div class="panel">
            <div class="panel-h">Rooms</div>

            <div class="panel-b">

                <?php if ($flash): ?>
                    <div class="msg <?= htmlspecialchars($flash['type']) ?>">
                        <?= htmlspecialchars($flash['msg']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="room_save.php" class="form-row">
                    <div class="field">
                        <label>Room Name</label>
                        <input name="room_name" placeholder="e.g. Lecture Room 1" required>
                    </div>
                    <input type="hidden" name="redirect" value="devices.php?tab=rooms">
                    <button type="submit">Add Room</button>
                </form>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:90px">ID</th>
                                <th>Room</th>
                                <th style="width:90px">Edit</th>
                                <th style="width:120px">Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($rooms) === 0): ?>
                                <tr>
                                    <td colspan="3">No rooms found.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($rooms as $r): ?>
                                <tr>
                                    <td><?= (int) $r['room_id'] ?></td>
                                    <td><?= htmlspecialchars($r['room_name']) ?></td>
                                    <td>
                                        <a href="room_edit.php?id=<?= (int)$r['room_id'] ?>&redirect=devices.php?tab=rooms" style="display:inline-block;padding:6px 14px;border-radius:8px;background:#2563eb;color:#fff;text-decoration:none;font-weight:900;">Edit</a>
                                    </td>
                                    <td>
                                        <form method="POST" action="room_delete.php" onsubmit="return confirm('Delete room?');" style="display:inline;">
                                            <input type="hidden" name="id" value="<?= (int)$r['room_id'] ?>">
                                            <input type="hidden" name="redirect" value="devices.php?tab=rooms">
                                            <button class="btn-del" type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

</body>

</html>