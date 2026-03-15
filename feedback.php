<?php
require_once 'config/database.php';

$ticket_number = $_GET['ticket'] ?? '';
$tracking_code = $_GET['code'] ?? '';
$error = '';
$success = '';
$ticket = null;

if (empty($ticket_number) || empty($tracking_code)) {
    die("Invalid feedback link. Please check your email for the correct link.");
}

try {
    // FIX: Removed 'client_tracking_code' from query as it does not exist in the DB schema provided
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE ticket_number = ? AND tracking_code = ? AND is_client = 1");
    $stmt->execute([$ticket_number, $tracking_code]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        $error = "Ticket not found or invalid security code. Please ensure you are using the link provided in your resolution email.";
    } elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
        $rating = intval($_POST['rating'] ?? 0);
        $comments = trim($_POST['feedback_comments'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $error = "Please select a star rating (1-5) before submitting.";
        } else {
            // Update the ticket with the feedback
            $update_stmt = $pdo->prepare("UPDATE tickets SET rating = ?, feedback_comments = ?, updated_at = NOW() WHERE id = ?");
            $update_stmt->execute([$rating, $comments, $ticket['id']]);

            $success = "Thank you! Your feedback has been submitted successfully. We appreciate your input!";

            // Update local ticket array to show the "Already Rated" state immediately
            $ticket['rating'] = $rating;
            $ticket['feedback_comments'] = $comments;
        }
    }
} catch (PDOException $e) {
    $error = "System Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Your Experience - ServiceLink</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/green.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-success: #10b981;
            --brand-dark: #059669;
            --bg-color: #f3f4f6;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-color);
            color: #111827;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .feedback-container {
            max-width: 650px;
            margin: 4rem auto;
            width: 100%;
        }

        .card-custom {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .header-custom {
            background: linear-gradient(135deg, var(--brand-success) 0%, var(--brand-dark) 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }

        /* Star Rating RTL Logic */
        .star-rating {
            direction: rtl;
            display: inline-block;
            padding: 10px;
        }

        .star-rating input[type=radio] {
            display: none;
        }

        .star-rating label {
            color: #e5e7eb;
            font-size: 3.5rem;
            padding: 0 5px;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }

        .star-rating label:hover,
        .star-rating label:hover~label,
        .star-rating input[type=radio]:checked~label {
            color: #f59e0b;
        }

        .form-control {
            border-radius: 12px;
            padding: 1.2rem;
            border: 1px solid #e5e7eb;
            background-color: #f9fafb;
            font-size: 1rem;
        }

        .form-control:focus {
            background-color: #ffffff;
            border-color: var(--brand-success);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        }

        .btn-submit {
            background: var(--brand-success);
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            border-radius: 12px;
            padding: 1.2rem;
            width: 100%;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: var(--brand-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px rgba(16, 185, 129, 0.2);
        }

        .static-stars {
            color: #f59e0b;
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>

<body>
    <div class="container feedback-container">
        <div class="card-custom">
            <div class="header-custom">
                <div class="d-inline-flex align-items-center justify-content-center bg-white bg-opacity-25 rounded-circle mb-3 shadow-sm" style="width: 70px; height: 70px;">
                    <i class="fas fa-star fa-2x text-white"></i>
                </div>
                <h2 class="fw-bold mb-1">Service Feedback</h2>
                <p class="mb-0 text-white-50">Reference Ticket: #<?php echo htmlspecialchars($ticket_number); ?></p>
            </div>

            <div class="p-4 p-md-5">
                <?php if ($error): ?>
                    <div class="alert alert-danger rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center p-3">
                        <i class="fas fa-exclamation-triangle fs-4 me-3"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center p-3">
                        <i class="fas fa-check-circle fs-4 me-3"></i>
                        <div><?php echo htmlspecialchars($success); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($ticket): ?>
                    <?php if (!empty($ticket['rating'])): ?>
                        <!-- Already Rated State -->
                        <div class="text-center py-4">
                            <h4 class="fw-bold text-dark mb-2">Thank You!</h4>
                            <div class="static-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $ticket['rating'] ? '' : 'text-light'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="text-muted fs-5 mb-4">You have already rated this service <strong><?php echo $ticket['rating']; ?> / 5</strong>.</p>

                            <?php if (!empty($ticket['feedback_comments'])): ?>
                                <div class="bg-light p-4 rounded-3 text-start border italic text-secondary mb-4">
                                    "<?php echo nl2br(htmlspecialchars($ticket['feedback_comments'])); ?>"
                                </div>
                            <?php endif; ?>

                            <div class="mt-4 pt-4 border-top">
                                <a href="client_submit.php" class="btn btn-outline-success rounded-pill px-4 fw-bold">Submit A New Request</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Submission Form -->
                        <form method="POST">
                            <div class="text-center mb-5">
                                <h5 class="fw-bold text-dark mb-3">How was your overall experience?</h5>
                                <div class="star-rating">
                                    <input id="star5" name="rating" type="radio" value="5" required>
                                    <label for="star5" title="Excellent"><i class="fas fa-star"></i></label>

                                    <input id="star4" name="rating" type="radio" value="4">
                                    <label for="star4" title="Good"><i class="fas fa-star"></i></label>

                                    <input id="star3" name="rating" type="radio" value="3">
                                    <label for="star3" title="Average"><i class="fas fa-star"></i></label>

                                    <input id="star2" name="rating" type="radio" value="2">
                                    <label for="star2" title="Poor"><i class="fas fa-star"></i></label>

                                    <input id="star1" name="rating" type="radio" value="1">
                                    <label for="star1" title="Terrible"><i class="fas fa-star"></i></label>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="feedback_comments" class="form-label fw-bold text-dark">Additional Comments (Optional)</label>
                                <textarea class="form-control" id="feedback_comments" name="feedback_comments" rows="5" placeholder="Please share any details that could help us improve our service..."></textarea>
                            </div>

                            <button type="submit" name="submit_feedback" class="btn btn-submit shadow-sm">
                                <i class="fas fa-paper-plane me-2"></i> Submit My Rating
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-center py-4 text-muted small mt-4">
            &copy; <?php echo date('Y'); ?> ServiceLink Ticketing System. All rights reserved.
        </div>
    </div>
</body>

</html>