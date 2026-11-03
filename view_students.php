<?php
/*
 * view_students.php - List every student (the R in CRUD)
 *
 * This page does three things at once:
 *   SEARCH     - one text box that looks in name, email and phone
 *   SORT       - click a column heading to sort by it
 *   PAGINATION - 10 students per page with Previous/Next buttons
 *
 * All three are kept in the URL (?search=...&sort=...&page=...) so a
 * particular view can be bookmarked or shared, and so clicking page 2
 * does not forget what you searched for.
 */

require_once 'includes/functions.php';
require_once 'includes/db.php';

require_login();

$page_title = 'View Students';

// ------------------------------------------------------------------
// 1. Read the settings out of the URL
// ------------------------------------------------------------------

$search = get_text('search');

// How many rows on one page
$per_page = 10;

// Which page are we on? max(1, ...) stops ?page=-5 or ?page=abc
$page = max(1, (int) get_text('page', '1'));

// ------------------------------------------------------------------
// 2. Work out the ORDER BY
//
// We can NOT put $_GET['sort'] straight into the SQL, because column
// names cannot be bound with a ? placeholder. Instead we use the value
// from the URL as a KEY into a list we wrote ourselves. If the key is
// not in the list we fall back to the default, so only these exact
// strings can ever reach the query.
// ------------------------------------------------------------------

$sortable = [
    'name'       => 'name',
    'email'      => 'email',
    'phone'      => 'phone',
    'created_at' => 'created_at',
    'id'         => 'id',
];

$sort_key = get_text('sort', 'created_at');

if (!isset($sortable[$sort_key])) {
    $sort_key = 'created_at';          // anything unexpected falls back
}
$sort_column = $sortable[$sort_key];   // guaranteed to be one of our five

// Same idea for the direction: only two values are possible
$direction = (strtolower(get_text('dir', 'desc')) === 'asc') ? 'ASC' : 'DESC';

// ------------------------------------------------------------------
// 3. Build the WHERE part for the search
//
// LIKE with % on both sides means "contains". The % signs go in the
// VALUE, not in the SQL, so the search term is still a bound parameter
// and still safe from SQL injection.
// ------------------------------------------------------------------

$where  = '';
$params = [];

if ($search !== '') {
    $where = 'WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?';
    $like  = '%' . $search . '%';
    $params = [$like, $like, $like];
}

// ------------------------------------------------------------------
// 4. Count the matching rows first, so we know how many pages there are
// ------------------------------------------------------------------

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM students $where");
$count_stmt->execute($params);
$total_rows = (int) $count_stmt->fetchColumn();

$total_pages = max(1, (int) ceil($total_rows / $per_page));

// If someone asks for page 99 of a 2 page list, show the last page
if ($page > $total_pages) {
    $page = $total_pages;
}

// OFFSET = how many rows to skip. Page 1 skips 0, page 2 skips 10, ...
$offset = ($page - 1) * $per_page;

// ------------------------------------------------------------------
// 5. Fetch just this page of students
//
// LIMIT and OFFSET are cast to int above, and $sort_column / $direction
// came from our whitelist, so it is safe to put them in the string.
// ------------------------------------------------------------------

$sql = "SELECT id, name, email, phone, address, profile_picture, created_at
        FROM students
        $where
        ORDER BY $sort_column $direction
        LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

/**
 * Build a link to this same page but with some settings changed.
 * Saves us writing the whole query string out by hand every time.
 */
function page_url($overrides = [])
{
    $query = array_merge([
        'search' => get_text('search'),
        'sort'   => get_text('sort', 'created_at'),
        'dir'    => get_text('dir', 'desc'),
        'page'   => max(1, (int) get_text('page', '1')),
    ], $overrides);

    // Drop empty values so the URL stays tidy
    $query = array_filter($query, function ($v) { return $v !== '' && $v !== null; });

    return 'view_students.php?' . http_build_query($query);
}

/**
 * Print one sortable column heading.
 * Clicking the heading you are already sorting by flips the direction.
 */
function sort_header($label, $key, $current_key, $direction)
{
    $is_active = ($key === $current_key);

    // If this column is active, the link should flip the order
    $next_dir = ($is_active && $direction === 'ASC') ? 'desc' : 'asc';

    $icon = '<i class="bi bi-arrow-down-up text-muted small ms-1"></i>';
    if ($is_active) {
        $icon = $direction === 'ASC'
            ? '<i class="bi bi-sort-up ms-1"></i>'
            : '<i class="bi bi-sort-down ms-1"></i>';
    }

    // page => 1 because after re-sorting, page 5 of the old order is meaningless
    $url = page_url(['sort' => $key, 'dir' => $next_dir, 'page' => 1]);

    echo '<a href="' . e($url) . '" class="text-decoration-none text-dark">'
       . e($label) . $icon . '</a>';
}

include 'includes/header.php';
?>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3 gap-2">
    <h3 class="mb-0"><i class="bi bi-people-fill me-2"></i>Student Records</h3>
    <a href="add_student.php" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i>Add Student
    </a>
</div>

<!-- ===== Search box ===== -->
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <!-- method="get" so the search ends up in the URL and can be bookmarked -->
        <form method="get" action="view_students.php" class="row g-2 align-items-end">

            <!-- Keep the current sort when searching -->
            <input type="hidden" name="sort" value="<?= e($sort_key) ?>">
            <input type="hidden" name="dir"  value="<?= e(strtolower($direction)) ?>">

            <div class="col-12 col-md-8">
                <label for="searchInput" class="form-label mb-1">
                    Search by name, email or phone
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="searchInput" name="search"
                           value="<?= e($search) ?>" placeholder="Start typing a name...">
                </div>
            </div>

            <div class="col-6 col-md-2">
                <button type="submit" class="btn btn-primary w-100">Search</button>
            </div>

            <div class="col-6 col-md-2">
                <a href="view_students.php" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- ===== Result summary ===== -->
<p class="text-muted small">
    <?php if ($search !== ''): ?>
        Found <strong><?= $total_rows ?></strong>
        student<?= $total_rows === 1 ? '' : 's' ?>
        matching &ldquo;<?= e($search) ?>&rdquo;.
    <?php else: ?>
        Showing <strong><?= count($students) ?></strong> of
        <strong><?= $total_rows ?></strong> students
        (page <?= $page ?> of <?= $total_pages ?>).
    <?php endif; ?>
</p>

<?php if (empty($students)): ?>

    <div class="alert alert-warning">
        <i class="bi bi-inbox me-1"></i>
        <?php if ($search !== ''): ?>
            No students matched your search. Try a different word, or
            <a href="view_students.php">show all students</a>.
        <?php else: ?>
            There are no students yet.
            <a href="add_student.php">Add the first one</a>.
        <?php endif; ?>
    </div>

<?php else: ?>

    <!-- table-responsive makes the table scroll sideways on a phone
         instead of breaking the layout -->
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:70px;">Photo</th>
                        <th><?php sort_header('Name', 'name', $sort_key, $direction); ?></th>
                        <th class="d-none d-sm-table-cell">
                            <?php sort_header('Email', 'email', $sort_key, $direction); ?>
                        </th>
                        <th><?php sort_header('Phone', 'phone', $sort_key, $direction); ?></th>
                        <th class="d-none d-lg-table-cell">Address</th>
                        <th class="d-none d-md-table-cell">
                            <?php sort_header('Added', 'created_at', $sort_key, $direction); ?>
                        </th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td>
                            <?php if (!empty($student['profile_picture'])): ?>
                                <img src="uploads/<?= e($student['profile_picture']) ?>"
                                     alt="<?= e($student['name']) ?>"
                                     class="rounded-circle avatar">
                            <?php else: ?>
                                <!-- No picture uploaded: show the first letter instead -->
                                <span class="avatar avatar-placeholder rounded-circle">
                                    <?= e(mb_strtoupper(mb_substr($student['name'], 0, 1))) ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="fw-semibold"><?= e($student['name']) ?></td>

                        <!-- Hidden on the narrowest phones so the Edit and Delete
                             buttons stay on screen without sideways scrolling -->
                        <td class="d-none d-sm-table-cell">
                            <a href="mailto:<?= e($student['email']) ?>"
                               class="text-decoration-none"><?= e($student['email']) ?></a>
                        </td>

                        <td><?= e($student['phone']) ?></td>

                        <td class="d-none d-lg-table-cell small text-muted">
                            <?= e($student['address']) ?>
                        </td>

                        <td class="d-none d-md-table-cell small text-muted">
                            <?= e(date('d M Y', strtotime($student['created_at']))) ?>
                        </td>

                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="update_student.php?id=<?= (int) $student['id'] ?>"
                                   class="btn btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                <!--
                                    Delete is a FORM, not a link, because a link is a
                                    GET request and a GET request should never change
                                    data (a browser or crawler could follow it by itself).
                                    js/main.js attaches the confirmation prompt
                                    via the js-delete-form class.
                                -->
                                <form method="post" action="delete_student.php"
                                      class="js-delete-form d-inline">
                                    <input type="hidden" name="id" value="<?= (int) $student['id'] ?>">
                                    <input type="hidden" name="student_name" value="<?= e($student['name']) ?>">
                                    <button type="submit" class="btn btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== Pagination ===== -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-3" aria-label="Student pages">
            <ul class="pagination justify-content-center flex-wrap">

                <!-- Previous -->
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e(page_url(['page' => $page - 1])) ?>">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                </li>

                <!-- Numbered pages. With a lot of pages we only show a
                     window of 2 either side of the current one, plus
                     the first and last, so the row never gets too long. -->
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == 1 || $i == $total_pages || abs($i - $page) <= 2): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= e(page_url(['page' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php elseif (abs($i - $page) === 3): ?>
                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    <?php endif; ?>
                <?php endfor; ?>

                <!-- Next -->
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e(page_url(['page' => $page + 1])) ?>">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
