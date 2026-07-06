<?php
/**
 * Report 4: Patients Report with Check-ins & Check-outs
 */
?>
<style>
    .table td, .table th { color: #212529 !important; }
    .reports-table thead th { background:#5156be; color:#fff; border-color:#5156be; }
    .btn-bo-primary { background:#5156be; border-color:#5156be; color:#fff !important; }
    .btn-bo-primary:hover, .btn-bo-primary:focus, .btn-bo-primary:active { background:#4148a8; border-color:#4148a8; color:#fff !important; }
    .badge.bg-success { background-color:#0ab39c !important; color:#fff !important; }
    .badge.bg-secondary { background-color:#6c757d !important; color:#fff !important; }
    @media print { .btn, #filter-card { display:none !important; } .card { border:none; box-shadow:none; } }
</style>

<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Patients Report with Check-ins &amp; Check-outs</h4>
                        <a href="<?php echo base_url('Orderportal/Reports/adminDashboard?from_date=' . urlencode($from_date) . '&to_date=' . urlencode($to_date)); ?>" class="btn btn-sm btn-light">
                            <i class="ri-arrow-left-line"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <div class="row" id="filter-card">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <form action="<?php echo base_url('Orderportal/Reports/patientsCheckinCheckoutReport'); ?>" method="POST">
                                <div class="row align-items-end g-3">
                                    <div class="col-md-3 col-sm-6">
                                        <label class="form-label">From Date</label>
                                        <input type="date" class="form-control" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" required>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <label class="form-label">To Date</label>
                                        <input type="date" class="form-control" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" required>
                                    </div>
                                    <div class="col-md-6 col-sm-12 d-flex">
                                        <button type="submit" class="btn btn-bo-primary me-2"><i class="ri-filter-3-line"></i> Apply Filter</button>
                                        <button type="button" class="btn btn-success me-2" onclick="exportReport()"><i class="ri-file-excel-line"></i> Export to Excel</button>
                                        <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="ri-printer-line"></i> Print</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                Patients
                                <span class="text-muted" style="font-size:13px;">(<?php echo date('d M Y', strtotime($from_date)); ?> &ndash; <?php echo date('d M Y', strtotime($to_date)); ?>)</span>
                            </h5>
                            <span class="badge bg-primary" style="font-size:13px;">Total Patients: <?php echo count($records); ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle reports-table" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th style="width:5%;">#</th>
                                            <th style="width:22%;">Patient Name</th>
                                            <th style="width:10%;">Suite No</th>
                                            <th style="width:19%;">Check-in</th>
                                            <th style="width:19%;">Check-out</th>
                                            <th style="width:10%;">Status</th>
                                            <th style="width:15%;">Duration</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($records)): ?>
                                            <?php $i = 1; foreach ($records as $rec): $f = $rec['_fmt']; ?>
                                                <tr>
                                                    <td><?php echo $i++; ?></td>
                                                    <td><?php echo htmlspecialchars($rec['patient_name'] ?: 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($rec['suite_number'] ?: 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($f['checkin']); ?></td>
                                                    <td><?php echo htmlspecialchars($f['checkout']); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $f['status'] === 'Active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                            <?php echo $f['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($f['duration']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="7" class="text-center text-muted">No records found for the selected date range.</td></tr>
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

<form id="exportForm" action="<?php echo base_url('Orderportal/Reports/exportPatientsCheckinCheckout'); ?>" method="POST" style="display:none;">
    <input type="hidden" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
    <input type="hidden" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
</form>
<script>
    function exportReport() { document.getElementById('exportForm').submit(); }
</script>
