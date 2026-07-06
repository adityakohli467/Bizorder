<?php
/**
 * Admin Reports Dashboard
 * Consolidated landing page for admin users.
 */
$base = base_url('Orderportal/Reports');
$rangeQuery = 'from_date=' . urlencode($from_date) . '&to_date=' . urlencode($to_date);

// Report catalogue (name, description, controller method)
$reports = [
    [
        'name' => 'Total Number of Active Patients',
        'desc' => 'View the total number of active patients for the selected date range.',
        'url'  => $base . '/activePatientsReport?' . $rangeQuery,
    ],
    [
        'name' => 'Total Number of Patients Ordered Food',
        'desc' => 'View the total number of unique patients who ordered food for the selected date range.',
        'url'  => $base . '/foodOrdersReport?' . $rangeQuery,
    ],
    [
        'name' => 'Total Number of Check-ins & Check-outs in the Day',
        'desc' => 'View the total number of check-ins and check-outs for each day within the selected date range.',
        'url'  => $base . '/checkinCheckoutCountReport?' . $rangeQuery,
    ],
    [
        'name' => 'Patients Report with Check-ins & Check-outs',
        'desc' => 'View detailed report of patients with their check-in and check-out details within the selected date range.',
        'url'  => $base . '/patientsCheckinCheckoutReport?' . $rangeQuery,
    ],
];
?>
<style>
    /* BizOrder theme colours */
    :root {
        --bo-primary: #5156be;
        --bo-teal:    #0ab39c;
        --bo-red:     #f06548;
        --bo-amber:   #f7b84b;
        --bo-blue:    #299cdb;
    }
    .stat-card {
        border: 1px solid #e9eaf2;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 2px 10px rgba(56, 65, 74, 0.06);
        transition: transform .15s ease, box-shadow .15s ease;
        height: 100%;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 22px rgba(56, 65, 74, 0.12);
    }
    .stat-card .stat-label {
        font-size: 13px;
        font-weight: 600;
        color: #878a99;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: .3px;
    }
    .stat-card .stat-value {
        font-size: 30px;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 6px;
    }
    .stat-card .stat-sub {
        font-size: 12.5px;
        color: #878a99;
        margin-bottom: 0;
    }
    .stat-card.tint-onboard   { background: #f5f6fe; }
    .stat-card.tint-discharge { background: #fef4f2; }
    .stat-card.tint-active    { background: #f1f8fd; }
    .stat-card.tint-food      { background: #fefaf1; }

    .val-onboard  { color: var(--bo-primary); }
    .val-discharge{ color: var(--bo-red); }
    .val-active   { color: var(--bo-blue); }
    .val-food     { color: var(--bo-amber); }
    .val-range    { color: var(--bo-primary); }

    .section-title { font-size: 16px; font-weight: 700; color: #212529; margin: 6px 0 12px; }

    .reports-table thead th {
        background: var(--bo-primary);
        color: #fff;
        font-weight: 600;
        border-color: var(--bo-primary);
    }
    .reports-table td { color: #212529; vertical-align: middle; }
    .btn-bo-primary {
        background: var(--bo-primary);
        border-color: var(--bo-primary);
        color: #fff !important;
    }
    .btn-bo-primary:hover,
    .btn-bo-primary:focus,
    .btn-bo-primary:active { background: #4148a8; border-color: #4148a8; color: #fff !important; }
</style>

<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

            <!-- Page Title -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Order Reports</h4>
                    </div>
                </div>
            </div>

            <!-- Quick Stats (Today) -->
            <h5 class="section-title">Quick Stats (Today)</h5>
            <div class="row g-3 mb-2">
                <div class="col-lg-3 col-sm-6">
                    <div class="stat-card tint-onboard p-3">
                        <div class="stat-label">Onboarded Today</div>
                        <div class="stat-value val-onboard"><?php echo (int) $today_onboarded; ?></div>
                        <p class="stat-sub">New patients onboarded</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="stat-card tint-discharge p-3">
                        <div class="stat-label">Discharged Today</div>
                        <div class="stat-value val-discharge"><?php echo (int) $today_discharged; ?></div>
                        <p class="stat-sub">Patients discharged</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="stat-card tint-active p-3">
                        <div class="stat-label">Active Patients Today</div>
                        <div class="stat-value val-active"><?php echo (int) $today_active; ?></div>
                        <p class="stat-sub">Currently active patients</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="stat-card tint-food p-3">
                        <div class="stat-label">Food Orders Today</div>
                        <div class="stat-value val-food"><?php echo ($today_food_orders === '' ? '&ndash;' : (int) $today_food_orders); ?></div>
                        <p class="stat-sub">No food orders today</p>
                    </div>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form action="<?php echo base_url('Orderportal/Reports/adminDashboard'); ?>" method="POST" id="rangeForm">
                                <div class="row align-items-end g-3">
                                    <div class="col-md-3 col-sm-6">
                                        <label class="form-label">From Date</label>
                                        <input type="date" class="form-control" name="from_date" id="from_date"
                                               value="<?php echo htmlspecialchars($from_date); ?>" required>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <label class="form-label">To Date</label>
                                        <input type="date" class="form-control" name="to_date" id="to_date"
                                               value="<?php echo htmlspecialchars($to_date); ?>" required>
                                    </div>
                                    <div class="col-md-6 col-sm-12 d-flex">
                                        <button type="submit" class="btn btn-bo-primary me-2">
                                            <i class="ri-filter-3-line"></i> Apply Filter
                                        </button>
                                        <a href="<?php echo base_url('Orderportal/Reports/adminDashboard'); ?>" class="btn btn-light">
                                            Reset
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats for Selected Range -->
            <h5 class="section-title">
                Stats for <?php echo date('d/m/Y', strtotime($from_date)); ?> - <?php echo date('d/m/Y', strtotime($to_date)); ?>
            </h5>
            <div class="row g-3 mb-2">
                <div class="col-lg-3 col-sm-6">
                    <div class="stat-card tint-onboard p-3">
                        <div class="stat-label">Onboarded</div>
                        <div class="stat-value val-onboard"><?php echo (int) $range_onboarded; ?></div>
                        <p class="stat-sub">New patients onboarded</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="stat-card tint-discharge p-3">
                        <div class="stat-label">Discharged</div>
                        <div class="stat-value val-discharge"><?php echo (int) $range_discharged; ?></div>
                        <p class="stat-sub">Patients discharged</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="stat-card tint-active p-3">
                        <div class="stat-label">Active Patients</div>
                        <div class="stat-value val-active"><?php echo (int) $range_active; ?></div>
                        <p class="stat-sub">Active patient-days</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="stat-card tint-food p-3">
                        <div class="stat-label">Food Orders</div>
                        <div class="stat-value val-food"><?php echo ($range_food_orders === '' ? '&ndash;' : (int) $range_food_orders); ?></div>
                        <p class="stat-sub">Patients ordered food</p>
                    </div>
                </div>
            </div>

            <!-- Reports -->
            <h5 class="section-title">Reports</h5>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle reports-table mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:32%;">Report Name</th>
                                            <th>Description</th>
                                            <th style="width:150px;" class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reports as $rep): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($rep['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($rep['desc']); ?></td>
                                                <td class="text-center">
                                                    <a href="<?php echo $rep['url']; ?>" class="btn btn-sm btn-bo-primary">
                                                        View Report
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
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
    document.getElementById('rangeForm').addEventListener('submit', function (e) {
        var from = document.getElementById('from_date').value;
        var to   = document.getElementById('to_date').value;
        if (from && to && from > to) {
            e.preventDefault();
            alert('From Date cannot be later than To Date.');
        }
    });
</script>
