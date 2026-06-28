<?php
// ============================================================
//  FacultyReview — teacher_detail.php
//  Teacher profile + aggregate ratings + approved review feed.
//  Mirrors course_detail.php. Reviews sorted by helpful votes DESC.
// ============================================================
require_once 'db.php';
requireLogin();
require_once 'navbar.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = (int)$_SESSION['user_id'];

$teacherId = (int)($_GET['id'] ?? 0);
if (!$teacherId) redirect('search.php');

// ── Teacher profile ──
$stmt = $mysqli->prepare("SELECT id, name, designation, email, bio FROM teachers WHERE id = ?");
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) redirect('search.php');

// ── Aggregate ratings (approved only) ──
$stmt = $mysqli->prepare("
    SELECT
        COUNT(*) AS cnt,
        AVG(rating_overall)  AS avg_overall,
        AVG(rating_teaching) AS avg_teaching,
        AVG(rating_workload) AS avg_workload,
        AVG(rating_grading)  AS avg_grading
    FROM reviews
    WHERE teacher_id = ? AND is_approved = 1
");
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$agg = $stmt->get_result()->fetch_assoc();
$stmt->close();

$reviewCount = (int)$agg['cnt'];

// ── Review feed (approved only, sorted by helpful votes DESC) ──
$stmt = $mysqli->prepare("
    SELECT
        r.id, r.comment, r.rating_overall, r.rating_teaching, r.rating_workload,
        r.rating_grading, r.created_at, r.user_id, r.semester_taken,
        c.id AS course_id, c.code AS course_code, c.name AS course_name,
        s.label AS session_label,
        (SELECT COUNT(*) FROM review_votes v WHERE v.review_id = r.id AND v.vote = 'helpful')     AS helpful_count,
        (SELECT COUNT(*) FROM review_votes v WHERE v.review_id = r.id AND v.vote = 'not_helpful') AS not_helpful_count,
        (SELECT vote FROM review_votes v WHERE v.review_id = r.id AND v.user_id = ?)               AS my_vote
    FROM reviews r
    JOIN courses c ON c.id = r.course_id
    JOIN sessions s ON s.id = r.session_id
    WHERE r.teacher_id = ? AND r.is_approved = 1
    ORDER BY helpful_count DESC, r.created_at DESC
");
$stmt->bind_param('ii', $userId, $teacherId);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$initials = '';
foreach (explode(' ', $teacher['name']) as $part) {
    $part = preg_replace('/[^A-Za-z]/', '', $part);
    if ($part !== '') $initials .= strtoupper($part[0]);
    if (strlen($initials) >= 2) break;
}
$designColor = designationColor($teacher['designation']);
$csrf = csrfToken();

navbarHeader($teacher['name'], '', 'search.php', $teacher['designation']);
?>
<style>
    /* ── Teacher profile card ── */
    .profile-card {
        background: var(--card); border-radius: var(--radius);
        box-shadow: var(--shadow); padding: 20px 18px;
        margin-bottom: 14px; display: flex; gap: 14px; align-items: flex-start;
    }
    .t-avatar {
        width: 64px; height: 64px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; font-weight: 800; color: #fff;
    }
    .t-info { flex: 1; min-width: 0; }
    .t-name { font-size: 1.1rem; font-weight: 800; margin-bottom: 2px; }
    .t-designation {
        display: inline-block; font-size: 0.74rem; font-weight: 700;
        padding: 3px 10px; border-radius: 20px; margin-bottom: 6px;
    }
    .t-dept { font-size: 0.78rem; color: var(--muted); margin-bottom: 6px; }
    .t-bio  { font-size: 0.83rem; color: var(--text); line-height: 1.5; }

    /* ── Aggregate ratings ── */
    .agg-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 16px; margin-bottom: 14px; }
    .agg-title { font-size: 0.78rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 10px; }
    .agg-row { display: flex; align-items: center; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid var(--border); }
    .agg-row:last-of-type { border-bottom: none; }
    .agg-label { font-size: 0.88rem; font-weight: 600; color: var(--text); }
    .agg-right { display: flex; align-items: center; gap: 8px; }
    .agg-stars { color: var(--warning); font-size: 0.95rem; letter-spacing: 1px; }
    .agg-num { font-size: 0.85rem; font-weight: 700; min-width: 28px; text-align: right; }
    .agg-footer { font-size: 0.74rem; color: var(--muted); text-align: center; margin-top: 8px; }
    .no-data { text-align: center; padding: 14px 0; color: var(--muted); font-size: 0.85rem; }

    .section-title { font-size: 1rem; font-weight: 700; margin-bottom: 12px; }

    /* ── Facebook-style post card ── */
    .post-card {
        background: var(--card);
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,.10);
        margin-bottom: 12px;
        overflow: hidden;
        border: 1px solid var(--border);
    }

    /* Post header */
    .post-header {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 14px 0;
    }
    .post-avatar {
        width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
        background: var(--brand);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem; font-weight: 700; color: #fff;
    }
    .post-meta { flex: 1; min-width: 0; }
    .post-name {
        font-size: 0.92rem; font-weight: 700; color: var(--text); line-height: 1.3;
    }
    .post-name a { color: var(--brand); text-decoration: none; }
    .post-name a:hover { text-decoration: underline; }
    .post-sub {
        font-size: 0.72rem; color: var(--muted); margin-top: 2px;
        display: flex; align-items: center; gap: 4px; flex-wrap: wrap;
    }
    .post-sub .dot { color: var(--border); }

    .star-badge {
        display: flex; align-items: center; gap: 3px;
        background: #fff8e1; border: 1px solid #ffe082;
        border-radius: 20px; padding: 4px 10px; flex-shrink: 0;
    }
    .star-badge .sb-star { color: #f5a623; font-size: 0.88rem; }
    .star-badge .sb-num  { font-size: 0.82rem; font-weight: 700; color: #5d4037; }
    .star-badge.low { background: #fff0f0; border-color: #ffcdd2; }
    .star-badge.low .sb-num { color: #b71c1c; }

    /* Post body */
    .post-body { padding: 10px 14px 12px; }
    .post-comment {
        font-size: 1.5rem; line-height: 1.65; color: var(--text);
        margin-bottom: 12px;
    }
    .post-comment.empty { color: var(--muted); font-style: italic; font-size: 0.9rem; }

    .rating-strip { display: flex; gap: 5px; }
    .r-chip {
        display: flex; align-items: center; gap: 4px; flex: 1;
        background: var(--bg); border-radius: 6px; padding: 6px 8px;
        font-size: 0.65rem;
    }
    .r-chip .rl { color: var(--muted); font-weight: 700; font-size: 0.60rem; }
    .r-chip .rs { color: var(--warning); font-size: 0.60rem; letter-spacing: 0.5px; }

    /* Reaction count row */
    .post-stats {
        display: flex; align-items: center; justify-content: space-between;
        padding: 7px 14px;
        font-size: 0.78rem; color: var(--muted);
        border-top: 1px solid var(--border);
    }
    .reaction-left { display: flex; align-items: center; gap: 5px; }

    /* Action buttons */
    .post-actions {
        display: flex;
        border-top: 1px solid var(--border);
    }
    .act-btn {
        flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px;
        padding: 9px 6px; border: none; background: none;
        font-size: 0.78rem; font-weight: 700; color: var(--muted);
        cursor: pointer; transition: background .12s; font-family: inherit;
    }
    .act-btn:hover { background: var(--bg); }
    .act-btn.act-helpful.voted     { color: #1877f2; }
    .act-btn.act-nothelpful.voted  { color: var(--danger); }
    .act-btn.act-flag { border-left: 1px solid var(--border); }
    .act-btn.act-flag:hover  { color: var(--danger); background: var(--danger-soft); }
    .act-btn.act-flag.flagged { color: var(--danger); }
    .own-label { font-size: 0.75rem; color: var(--brand); font-weight: 700; }

    /* Post footer: course tag */
    .post-footer {
        padding: 8px 14px 10px;
        border-top: 1px solid var(--border);
        background: var(--bg);
    }
    .course-tag {
        display: inline-flex; align-items: center; gap: 5px;
        background: var(--card); border: 1px solid var(--border);
        border-radius: 6px; padding: 5px 10px;
        font-size: 0.75rem; font-weight: 700; color: var(--brand);
        text-decoration: none;
    }
    .course-tag:hover { text-decoration: underline; }

    /* Empty state */
    .empty-state { background: var(--card); border-radius: var(--radius); padding: 36px 20px; text-align: center; box-shadow: var(--shadow); }
    .empty-emoji { font-size: 2rem; margin-bottom: 8px; }
    .empty-text  { font-size: 0.85rem; color: var(--muted); }
</style>

<div class="fr-container">

    <!-- Teacher profile -->
    <div class="profile-card">
        <div class="t-avatar" style="background: <?= e($designColor) ?>;"><?= e($initials ?: '?') ?></div>
        <div class="t-info">
            <div class="t-name"><?= e($teacher['name']) ?></div>
            <span class="t-designation" style="background: <?= e($designColor) ?>1A; color: <?= e($designColor) ?>;"><?= e($teacher['designation']) ?></span>
            <div class="t-dept">Dept. of Computer Science &amp; Engineering</div>
            <?php if (!empty(trim((string)$teacher['bio']))): ?>
                <div class="t-bio"><?= e($teacher['bio']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Aggregate ratings -->
    <div class="agg-card">
        <div class="agg-title">Aggregate Ratings</div>
        <?php if ($reviewCount === 0): ?>
            <div class="no-data">No approved reviews yet for this teacher.</div>
        <?php else: ?>
            <div class="agg-row">
                <span class="agg-label">Overall Rating</span>
                <span class="agg-right"><span class="agg-stars"><?= starDisplay((float)$agg['avg_overall']) ?></span><span class="agg-num"><?= number_format((float)$agg['avg_overall'], 1) ?></span></span>
            </div>
            <div class="agg-row">
                <span class="agg-label">Teaching Quality</span>
                <span class="agg-right"><span class="agg-stars"><?= starDisplay((float)$agg['avg_teaching']) ?></span><span class="agg-num"><?= number_format((float)$agg['avg_teaching'], 1) ?></span></span>
            </div>
            <div class="agg-row">
                <span class="agg-label">Workload</span>
                <span class="agg-right"><span class="agg-stars"><?= starDisplay((float)$agg['avg_workload']) ?></span><span class="agg-num"><?= number_format((float)$agg['avg_workload'], 1) ?></span></span>
            </div>
            <div class="agg-row">
                <span class="agg-label">Grading Fairness</span>
                <span class="agg-right"><span class="agg-stars"><?= starDisplay((float)$agg['avg_grading']) ?></span><span class="agg-num"><?= number_format((float)$agg['avg_grading'], 1) ?></span></span>
            </div>
            <div class="agg-footer">Based on <?= $reviewCount ?> approved review<?= $reviewCount == 1 ? '' : 's' ?></div>
        <?php endif; ?>
    </div>

    <div class="section-title">Reviews</div>

    <?php if (empty($reviews)): ?>
        <div class="empty-state">
            <div class="empty-emoji">💬</div>
            <div class="empty-text">No reviews yet for this teacher.</div>
        </div>
    <?php else: ?>
        <?php foreach ($reviews as $r):
            $isOwn   = ((int)$r['user_id'] === $userId);
            $myVote  = $r['my_vote'];
            $overall = (float)$r['rating_overall'];
            $isLow   = $overall < 3;
        ?>
        <div class="post-card" data-review-id="<?= (int)$r['id'] ?>">

            <!-- Header: course code + name + session info + star badge -->
            <div class="post-header">
                <div class="post-avatar"><?= e(strtoupper(substr(preg_replace('/[^A-Za-z ]/', '', $r['course_code']), 0, 2))) ?></div>
                <div class="post-meta">
                    <div class="post-name">
                        <a href="course_detail.php?id=<?= (int)$r['course_id'] ?>"><?= e($r['course_code']) ?> — <?= e($r['course_name']) ?></a>
                    </div>
                    <div class="post-sub">
                        <span>📅 <?= e($r['session_label']) ?></span>
                        <span class="dot">·</span>
                        <span><?= semesterLabel((int)$r['semester_taken']) ?></span>
                        <span class="dot">·</span>
                        <span>🌎 <?= timeAgo($r['created_at']) ?></span>
                    </div>
                </div>
                <div class="star-badge <?= $isLow ? 'low' : '' ?>">
                    <span class="sb-star">★</span>
                    <span class="sb-num"><?= number_format($overall, 1) ?></span>
                </div>
            </div>

            <!-- Body: comment + rating chips -->
            <div class="post-body">
                <?php if (trim((string)$r['comment']) !== ''): ?>
                    <div class="post-comment"><?= e($r['comment']) ?></div>
                <?php else: ?>
                    <div class="post-comment empty">No comment written.</div>
                <?php endif; ?>

                <div class="rating-strip">
                    <div class="r-chip">
                        <span class="rl">Teaching</span>
                        <span class="rs"><?= starDisplay((float)$r['rating_teaching']) ?></span>
                    </div>
                    <div class="r-chip">
                        <span class="rl">Workload</span>
                        <span class="rs"><?= starDisplay((float)$r['rating_workload']) ?></span>
                    </div>
                    <div class="r-chip">
                        <span class="rl">Grading</span>
                        <span class="rs"><?= starDisplay((float)$r['rating_grading']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Reaction counts -->
            <!-- <div class="post-stats">
                <div class="reaction-left">
                    <?php if ((int)$r['helpful_count'] > 0): ?>
                        👍 <?= (int)$r['helpful_count'] ?> found helpful
                    <?php else: ?>
                        <span>No helpful votes yet</span>
                    <?php endif; ?>
                </div>
                <?php if ((int)$r['not_helpful_count'] > 0): ?>
                    <span>👎 <?= (int)$r['not_helpful_count'] ?> not helpful</span>
                <?php endif; ?>
            </div> -->

            <!-- Action buttons -->
            <div class="post-actions">
                <?php if ($isOwn): ?>
                    <div class="act-btn" style="cursor:default;">
                        <span class="own-label">📝 Your review</span>
                    </div>
                <?php else: ?>
                    <button type="button"
                        class="act-btn act-helpful <?= $myVote === 'helpful' ? 'voted' : '' ?>"
                        onclick="castVote(<?= (int)$r['id'] ?>, 'helpful', this)">
                        👍 <span class="helpful-count"><?= (int)$r['helpful_count'] ?> Helpful</span>
                    </button>
                    <button type="button"
                        class="act-btn act-nothelpful <?= $myVote === 'not_helpful' ? 'voted' : '' ?>"
                        onclick="castVote(<?= (int)$r['id'] ?>, 'not_helpful', this)">
                        👎 <span class="not-helpful-count"><?= (int)$r['not_helpful_count'] ?> Not Helpful</span>
                    </button>
                    <!-- <button type="button"
                        class="act-btn act-flag"
                        onclick="flagReview(<?= (int)$r['id'] ?>, this)"
                        title="Flag this review">
                        🚩 Flag
                    </button> -->
                <?php endif; ?>
            </div>

            <!-- Footer: course tag link -->
            <!-- <div class="post-footer">
                <a href="course_detail.php?id=<?= (int)$r['course_id'] ?>" class="course-tag">
                    📚 <?= e($r['course_code']) ?> — <?= e($r['course_name']) ?>
                </a>
            </div> -->

        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
    const CSRF = <?= json_encode($csrf) ?>;

    async function castVote(reviewId, voteType, btn) {
        const card = btn.closest('.post-card');
        try {
            const res = await fetch('vote.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'review_id=' + encodeURIComponent(reviewId) + '&vote=' + encodeURIComponent(voteType) + '&csrf_token=' + encodeURIComponent(CSRF)
            });
            const data = await res.json();
            if (!data.success) { alert(data.message || 'Could not register vote.'); return; }

            const helpfulBtn    = card.querySelector('.act-btn.act-helpful');
            const notHelpfulBtn = card.querySelector('.act-btn.act-nothelpful');

            helpfulBtn.querySelector('.helpful-count').textContent      = data.helpful_count + ' Helpful';
            notHelpfulBtn.querySelector('.not-helpful-count').textContent = data.not_helpful_count + ' Not Helpful';

            helpfulBtn.classList.remove('voted');
            notHelpfulBtn.classList.remove('voted');

            if (data.my_vote === 'helpful')     helpfulBtn.classList.add('voted');
            if (data.my_vote === 'not_helpful') notHelpfulBtn.classList.add('voted');

            // Update stats row
            const statsLeft  = card.querySelector('.reaction-left');
            const statsRight = card.querySelector('.post-stats span:last-child');
            if (data.helpful_count > 0) {
                statsLeft.innerHTML = '👍 ' + data.helpful_count + ' found helpful';
            } else {
                statsLeft.innerHTML = '<span>No helpful votes yet</span>';
            }
            if (statsRight) {
                statsRight.textContent = data.not_helpful_count > 0 ? '👎 ' + data.not_helpful_count + ' not helpful' : '';
            }
        } catch (e) {
            alert('Network error. Please try again.');
        }
    }

    async function flagReview(reviewId, btn) {
        if (!confirm('Flag this review for admin attention?')) return;
        try {
            const res = await fetch('flag.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'review_id=' + encodeURIComponent(reviewId) + '&csrf_token=' + encodeURIComponent(CSRF)
            });
            const data = await res.json();
            if (data.success) {
                btn.textContent = '🚩 Flagged';
                btn.classList.add('flagged');
                btn.disabled = true;
                btn.title = 'Flagged — pending admin review';
            } else {
                alert(data.message || 'Could not flag this review.');
            }
        } catch (e) {
            alert('Network error. Please try again.');
        }
    }
</script>

<?php navbarFooter('student', 'search'); ?>