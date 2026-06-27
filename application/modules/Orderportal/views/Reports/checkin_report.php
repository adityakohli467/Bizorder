<style>
    .table td, .table th { color: #212529 !important; }
    .badge.bg-success { background-color: #198754 !important; color: #fff !important; }
    .badge.bg-secondary { background-color: #6c757d !important; color: #fff !important; }
    .badge.bg-primary { background-color: #0d6efd !important; color: #fff !important; }
    @media print {
        .btn, .breadcrumb, #filter-card { display: none !important; }
        .card { border: none; box-shadow: none; }
    }
</style>

<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

            <!-- Page Title -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Check-ins &amp; Active Customers</h4>
                        <a href="<?php echo base_url('Orderportal/Reports/index'); ?>" class="btn btn-sm btn-light">
                            <i class="ri-arrow-left-line"></i> Back to Reports
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="row" id="filter-card">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Filter</h5>
                        </div>
                        <div class="card-body">
                            <form action="<?php echo base_url('Orderportal/Reports/checkinReport'); ?>" method="POST">
                                <div class="row">
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <label class="form-label">From Date</label>
                                        <input type="date" class="form-control" name="from_date"
                                               value="<?php echo htmlspecialchars($from_date); ?>" required>
                                    </div>
                                    <div class="col-md-3 col-sm-6 mb-3">
                                        <label class="form-label">To Date</label>
                                        <input type="date" class="form-control" name="to_date"
                                               value="<?php echo htmlspecialchars($to_date); ?>" required>
                                    </div>
                                    <div class="col-md-6 col-sm-12 mb-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="ri-filter-3-line"></i> Filter
                                        </button>
                                        <button type="button" class="btn btn-success me-2" onclick="exportCheckinReport()">
                                            <i class="ri-file-excel-line"></i> Export CSV
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="window.print()">
                                            <i class="ri-printer-line"></i> Print
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="card border-0 shadow" style="background:#5156be;border-radius:8px;">
                        <div class="card-body p-3">
                            <p class="mb-1" style="color:rgba(255,255,255,.85);font-size:12px;font-weight:500;text-transform:uppercase;">Total Records</p>
                            <h3 class="mb-0" style="color:#fff;font-weight:700;"><?php echo (int) $total_records; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow" style="background:#0ab39c;border-radius:8px;">
                        <div class="card-body p-3">
                            <p class="mb-1" style="color:rgba(255,255,255,.85);font-size:12px;font-weight:500;text-transform:uppercase;">Active (Checked In)</p>
                            <h3 class="mb-0" style="color:#fff;font-weight:700;"><?php echo (int) $total_active; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow" style="background:#6c757d;border-radius:8px;">
                        <div class="card-body p-3">
                            <p class="mb-1" style="color:rgba(255,255,255,.85);font-size:12px;font-weight:500;text-transform:uppercase;">Checked Out</p>
                            <h3 class="mb-0" style="color:#fff;font-weight:700;"><?php echo (int) $total_checkedout; ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Table -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                Check-ins &amp; Active Customers
                                <span class="text-muted" style="font-size:13px;">
                                    (<?php echo date('d M Y', strtotime($from_date)); ?> &ndash; <?php echo date('d M Y', strtotime($to_date)); ?>)
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:5%;">#</th>
                                            <th style="width:18%;">Date</th>
                                            <th style="width:25%;">Customer Name</th>
                                            <th style="width:17%;">Check In Time</th>
                                            <th style="width:22%;">Check Out Time</th>
                                            <th style="width:13%;">Suite No</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($records)): ?>
                                            <?php $i = 1; foreach ($records as $row): ?>
                                                <?php
                                                    $has_checkout = (!empty($row['date_of_discharge']) && $row['date_of_discharge'] != '0000-00-00');
                                                    $checkin_date = !empty($row['date_onboarded'])
                                                        ? date('d M Y', strtotime($row['date_onboarded'])) : 'N/A';

                                                    if (!empty($row['time_onboarded']) && $row['time_onboarded'] != '0000-00-00 00:00:00') {
                                                        $checkin_time = date('d M Y h:i A', strtotime($row['time_onboarded']));
                                                    } elseif (!empty($row['date_onboarded'])) {
                                                        $checkin_time = date('d M Y', strtotime($row['date_onboarded']));
                                                    } else {
                                                        $checkin_time = null;
                                                    }

                                                    if (!empty($row['time_discharged']) && $row['time_discharged'] != '0000-00-00 00:00:00') {
                                                        $checkout = date('d M Y h:i A', strtotime($row['time_discharged']));
                                                    } elseif ($has_checkout) {
                                                        $checkout = date('d M Y', strtotime($row['date_of_discharge']));
                                                    } else {
                                                        $checkout = null;
                                                    }
                                                ?>
                                                <tr>
                                                    <td><?php echo $i++; ?></td>
                                                    <td><strong><?php echo $checkin_date; ?></strong></td>
                                                    <td><?php echo htmlspecialchars($row['patient_name'] ?: 'N/A'); ?></td>
                                                    <td>
                                                        <?php if ($checkin_time !== null): ?>
                                                            <span class="badge bg-primary"><?php echo $checkin_time; ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($checkout !== null): ?>
                                                            <span class="badge bg-secondary"><?php echo $checkout; ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Still Checked In</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary" style="font-size:13px;">
                                                            <?php echo htmlspecialchars($row['suite_number'] ?: 'N/A'); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">
                                                    No check-ins or active customers found for this date range.
                                                </td>
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
function exportCheckinReport() {
    var fromDate = document.querySelector('input[name="from_date"]').value;
    var toDate = document.querySelector('input[name="to_date"]').value;

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo base_url('Orderportal/Reports/exportCheckinReport'); ?>';

    var fromInput = document.createElement('input');
    fromInput.type = 'hidden';
    fromInput.name = 'from_date';
    fromInput.value = fromDate;
    form.appendChild(fromInput);

    var toInput = document.createElement('input');
    toInput.type = 'hidden';
    toInput.name = 'to_date';
    toInput.value = toDate;
    form.appendChild(toInput);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>
