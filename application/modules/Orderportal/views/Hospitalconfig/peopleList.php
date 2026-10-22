<?php
/**
 * Chef portal – Currently on-board patients listing.
 * Columns: Patient name, Suite, Floor, Allergies, Dietary, Date onboarded.
 */

// Helper: resolve a JSON array of foodmenuconfig IDs into readable names
if (!function_exists('resolveConfigNames')) {
    function resolveConfigNames($rawJson, $lookup) {
        $names = [];
        if (!empty($rawJson) && $rawJson !== 'null' && $rawJson !== '[]') {
            $ids = json_decode($rawJson, true);
            if (is_array($ids) && !empty($ids) && !empty($lookup)) {
                foreach ($ids as $id) {
                    foreach ($lookup as $row) {
                        if ($row['id'] == $id) { $names[] = $row['name']; break; }
                    }
                }
            }
        }
        return $names;
    }
}

// Floor id -> name lookup
$floorMap = [];
if (!empty($floorLists)) {
    foreach ($floorLists as $fl) { $floorMap[$fl['id']] = $fl['name']; }
}
?>
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                                <h4 class="mb-sm-0 text-black">On-board Patients</h4>
                                <div class="page-title-right">
                                    <div class="d-flex justify-content-sm-end gap-2 align-items-center">
                                        <span class="badge bg-success-subtle text-success fs-12 px-3 py-2">
                                            <i class="fa-solid fa-users me-1"></i>
                                            <?php echo isset($people) ? count($people) : 0; ?> Currently On-board
                                        </span>
                                        <a class="btn btn-outline-primary" href="<?php echo base_url('Orderportal/Hospitalconfig/List'); ?>">
                                            <i class="fas fa-bed me-1"></i>Manage Suites
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="position-relative" style="max-width: 360px;">
                                    <input type="text" id="peopleSearch" class="form-control" placeholder="Search by patient name, suite or floor...">
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-bordered align-middle mb-0" id="peopleTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Patient Name</th>
                                            <th>Suite</th>
                                            <th>Floor</th>
                                            <th>Allergies</th>
                                            <th>Dietary</th>
                                            <th>Date Onboarded</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($people)): ?>
                                            <?php foreach ($people as $i => $person): ?>
                                                <?php
                                                    $allergyNames = resolveConfigNames($person['allergies'] ?? '', isset($allergensData) ? $allergensData : []);
                                                    $dietaryNames = resolveConfigNames($person['dietary_preferences'] ?? '', isset($cuisineData) ? $cuisineData : []);
                                                    $suiteLabel   = !empty($person['bed_no']) ? 'Suite ' . $person['bed_no'] : '-';
                                                    $floorLabel   = (isset($person['floor']) && isset($floorMap[$person['floor']])) ? $floorMap[$person['floor']] : 'Unknown';
                                                    $onboarded    = !empty($person['date_onboarded']) ? date('d/m/Y', strtotime($person['date_onboarded'])) : '-';
                                                ?>
                                                <tr>
                                                    <td><?php echo $i + 1; ?></td>
                                                    <td class="fw-semibold text-dark"><?php echo htmlspecialchars($person['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($suiteLabel); ?></td>
                                                    <td><?php echo htmlspecialchars($floorLabel); ?></td>
                                                    <td>
                                                        <?php if (!empty($allergyNames)): ?>
                                                            <?php foreach ($allergyNames as $an): ?>
                                                                <span class="badge rounded-pill bg-danger-subtle text-danger me-1 mb-1"><?php echo htmlspecialchars($an); ?></span>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">None</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($dietaryNames)): ?>
                                                            <?php foreach ($dietaryNames as $dn): ?>
                                                                <span class="badge rounded-pill bg-success-subtle text-success me-1 mb-1"><?php echo htmlspecialchars($dn); ?></span>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">None</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($onboarded); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">No patients are currently on-board.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var search = document.getElementById('peopleSearch');
        if (!search) return;
        search.addEventListener('keyup', function () {
            var term = this.value.toLowerCase();
            var rows = document.querySelectorAll('#peopleTable tbody tr');
            rows.forEach(function (row) {
                if (row.querySelector('td[colspan]')) return;
                row.style.display = row.textContent.toLowerCase().indexOf(term) > -1 ? '' : 'none';
            });
        });
    })();
</script>
