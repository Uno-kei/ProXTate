<?php
require_once '../inc/db.php';
require_once '../inc/functions.php';
require_once '../inc/auth.php';

// Check if user is admin
checkPermission(['admin']);

$adminId = $_SESSION['user_id'];
$partnerId = isset($_GET['user']) ? intval($_GET['user']) : 0;

// Get partner data if partner ID is provided
$partnerData = null;
if ($partnerId > 0) {
    $sql = "SELECT id, full_name, email, role FROM users WHERE id = ?";
    $partnerData = fetchOne($sql, "i", [$partnerId]);
}

// Get all messages with this partner if exists
$messages = [];
if ($partnerData) {
    $sql = "SELECT m.*, 
            s.full_name as sender_name, 
            r.full_name as receiver_name 
            FROM messages m
            JOIN users s ON m.sender_id = s.id
            JOIN users r ON m.receiver_id = r.id
            WHERE (m.sender_id = ? AND m.receiver_id = ?) 
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC";

    $messages = fetchAll($sql, "iiii", [$adminId, $partnerId, $partnerId, $adminId]);
}

// Get unique conversation partners (combining reports and messages)
$sql = "SELECT DISTINCT u.id, u.full_name, u.email, u.role, COALESCE(r.created_at, m.created_at) as last_activity
        FROM users u
        LEFT JOIN reports r ON r.reporter_id = u.id
        LEFT JOIN messages m ON (m.sender_id = u.id AND m.receiver_id = ?) 
                            OR (m.receiver_id = u.id AND m.sender_id = ?)
        WHERE u.id != ? 
        AND (r.reporter_id IS NOT NULL OR m.id IS NOT NULL)
        GROUP BY u.id
        ORDER BY last_activity DESC";

$partners = fetchAll($sql, "iii", [$adminId, $adminId, $adminId]);

include '../inc/header.php';
?>

<div class="container py-5">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Admin Dashboard</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                    <a href="reports.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-flag me-2"></i> Reports
                    </a>
                    <a href="messages.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-comments me-2"></i> Messages
                    </a>
                    <a href="users.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i> Users
                    </a>
                    <a href="properties.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-home me-2"></i> Properties
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
            <div class="messaging-container">
                <!-- Partners List -->
                <div class="partners-list">
                    <div class="partners-header">
                        <h6 class="mb-0">Reporters</h6>
                    </div>
                    <div class="partners-body">
                        <?php foreach ($partners as $partner): ?>
                        <a href="?user=<?= $partner['id'] ?>" class="partner-item <?= $partnerId == $partner['id'] ? 'active' : '' ?>">
                            <div class="partner-avatar">
                                <?php 
                                $initial = strtoupper(substr($partner['full_name'], 0, 1));
                                $bgColor = generateAvatarColor($partner['id']);
                                ?>
                                <div class="avatar-circle" style="background-color: <?= $bgColor ?>;">
                                    <?= $initial ?>
                                </div>
                            </div>
                            <div class="partner-info">
                                <div class="partner-name">
                                    <span><?= htmlspecialchars($partner['full_name']) ?></span>
                                    <small class="partner-role"><?= ucfirst($partner['role']) ?></small>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Messages Area -->
                <div class="messages-area">
                    <?php if (!$partnerData): ?>
                        <div class="message-placeholder">
                            <div class="mb-3">
                                <i class="fas fa-comments text-primary fa-4x"></i>
                            </div>
                            <h5>Select a conversation</h5>
                            <p class="text-muted">Choose a reporter from the list to start messaging</p>
                        </div>
                    <?php else: ?>
                        <!-- Message Header -->
                        <div class="message-header">
                            <div class="d-flex align-items-center">
                                <?php 
                                $initial = strtoupper(substr($partnerData['full_name'], 0, 1));
                                $bgColor = generateAvatarColor($partnerData['id']);
                                ?>
                                <div class="avatar-circle me-2" style="background-color: <?= $bgColor ?>;">
                                    <?= $initial ?>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?= htmlspecialchars($partnerData['full_name']) ?></h6>
                                    <small class="text-muted"><?= ucfirst($partnerData['role']) ?></small>
                                </div>
                            </div>
                        </div>

                        <!-- Message Body -->
                        <div class="message-body" id="messageContainer">
                            <?php if (empty($messages)): ?>
                                <div class="text-center py-5">
                                    <div class="mb-3">
                                        <i class="fas fa-comments text-muted fa-3x"></i>
                                    </div>
                                    <p class="text-muted mb-0">No messages yet</p>
                                    <small class="text-muted">Start the conversation by sending a message</small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $message): 
                                    $isSender = $message['sender_id'] == $adminId;
                                    $messageClass = $isSender ? 'message-sent' : 'message-received';
                                ?>
                                <div class="message-item <?= $messageClass ?>">
                                    <div class="message-content">
                                        <p><?= nl2br(htmlspecialchars($message['message'])) ?></p>
                                        <small class="message-time"><?= formatMessageTime($message['created_at']) ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Message Footer -->
                        <div class="message-footer">
                            <form id="messageForm" method="POST" action="../api/messages.php">
                                <input type="hidden" name="action" value="send_message">
                                <input type="hidden" name="receiver_id" value="<?= $partnerData['id'] ?>">
                                <div class="input-group">
                                    <textarea class="form-control" name="message" placeholder="Type your message..." rows="1" required></textarea>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.messaging-container {
    display: flex;
    height: 500px;
    border: 1px solid #e0e0e0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.partners-list {
    width: 260px;
    border-right: 1px solid #e0e0e0;
    overflow-y: auto;
}

.messages-area {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.message-header {
    padding: 12px;
    border-bottom: 1px solid #e0e0e0;
    background-color: #f8f9fa;
}

.message-body {
    flex-grow: 1;
    overflow-y: auto;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.message-item {
    display: flex;
    margin-bottom: 6px;
}

.message-sent {
    justify-content: flex-end;
}

.message-received {
    justify-content: flex-start;
}

.message-content {
    padding: 13px 13px;
    border-radius: 16px;
    position: relative;
    max-width: 100%;
    word-wrap: break-word;
}

.message-sent .message-content {
    background-color: #0084ff;
    color: white;
    border-bottom-right-radius: 4px;
    margin-left: auto;
}

.message-received .message-content {
    background-color: #f0f2f5;
    color: #333;
    border-bottom-left-radius: 4px;
    margin-right: auto;
}

.message-content p {
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.4;
}

.message-time {
    font-size: 10px;
    opacity: 0.7;
    margin-top: 3px;
    display: block;
}

.message-footer {
    padding: 12px;
    border-top: 1px solid #e0e0e0;
    background-color: #fff;
}

.message-footer .form-control {
    border-radius: 20px;
    padding: 8px 16px;
    resize: none;
    max-height: 100px;
}

.message-footer .btn {
    border-radius: 50%;
    width: 36px;
    height: 36px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.partner-item {
    display: flex;
    align-items: center;
    padding: 10px 15px;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid #e0e0e0;
}

.partner-item:hover,
.partner-item.active {
    background-color: #f8f9fa;
}

.partner-avatar {
    margin-right: 10px;
}

.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.partner-info {
    flex-grow: 1;
}

.partner-name {
    font-weight: 500;
}

.partner-role {
    font-size: 12px;
    color: #6c757d;
}
</style>

<script src="../js/admin-messages.js"></script>
<?php include '../inc/footer.php'; ?>