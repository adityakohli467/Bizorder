<?php
/**
 * Report 3: Total Number of Check-ins & Check-outs in the Day
 */
?>
<style>
    .table td, .table th { color: #212529 !important; }
    .reports-table thead th { background:#5156be; color:#fff; border-color:#5156be; }
    .btn-bo-primary { background:#5156be; border-color:#5156be; color:#fff; }
    .btn-bo-primary:hover { background:#4148a8; border-color:#4148a8; color:#fff; }
    @media print { .btn, #filter-card { display:none !important; } .card { border:none; box-shadow:none; } }
</style>

<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Check-ins &amp; Check-outs in the Day</h4>
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
                            <form action="<?php echo base_url('Orderportal/Reports/checkinCheckoutCountReport'); ?>" method="POST">
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
                                Daily Check-ins &amp; Check-outs
                                <span class="text-muted" style="font-size:13px;">(<?php echo date('d M Y', strtotime($from_date)); ?> &ndash; <?php echo date('d M Y', strtotime($to_date)); ?>)</span>
                            </h5>
                            <span>
                                <span class="badge bg-success" style="font-size:13px;">Check-ins: <?php echo (int) $total_checkins; ?></span>
                                <span class="badge bg-secondary" style="font-size:13px;">Check-outs: <?php echo (int) $total_checkouts; ?></span>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle reports-table" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th style="width:34%;">Date</th>
                                            <th>Check-ins</th>
                                            <th>Check-outs</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($rows)): ?>
                                            <?php foreach ($rows as $r): ?>
                                                <tr>
                                                    <td><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                                                    <td><?php echo (int) $r['check_ins']; ?></td>
                                                    <td><?php echo (int) $r['check_outs']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="3" class="text-center text-muted">No records found for the selected date range.</td></tr>
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

<form id="exportForm" action="<?php echo base_url('Orderportal/Reports/exportCheckinCheckoutCount'); ?>" method="POST" style="display:none;">
    <input type="hidden" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
    <input type="hidden" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
</form>
<script>
    function exportReport() { document.getElementById('exportForm').submit(); }
</script>
