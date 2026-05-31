<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; margin: 0; padding: 0; }
        .header { background-color: #dc3545; color: #fff; padding: 15px 20px; border-radius: 6px 6px 0 0; }
        .header h2 { margin: 0; font-size: 18px; }
        .content { padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 6px 6px; }
        table.info { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.info th { background-color: #f8f9fa; padding: 10px 14px; border: 1px solid #ddd; text-align: left; font-size: 13px; width: 200px; }
        table.info td { padding: 10px 14px; border: 1px solid #ddd; font-size: 13px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th { background-color: #f8f9fa; padding: 8px 12px; border: 1px solid #ddd; text-align: left; font-size: 13px; }
        table.items td { padding: 8px 12px; border: 1px solid #ddd; font-size: 13px; }
        table.items tr:nth-child(even) { background-color: #fafafa; }
        .cancelled-badge { display: inline-block; background-color: #dc3545; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .no-cancel { color: #28a745; font-weight: bold; }
        .footer { margin-top: 20px; font-size: 12px; color: #888; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>&#9888; Patient Discharged</h2>
    </div>
    <div class="content">
        <p style="margin-top: 0;">A patient has been discharged. Please find the details below:</p>
        
        <table class="info">
            <tr>
                <th>Patient Name</th>
                <td><?php echo htmlspecialchars($patient_name); ?></td>
            </tr>
            <tr>
                <th>Suite / Room</th>
                <td><?php echo htmlspecialchars($suite_name); ?></td>
            </tr>
            <tr>
                <th>Discharge Time</th>
                <td><?php echo $discharge_time; ?></td>
            </tr>
            <tr>
                <th>Orders Cancelled</th>
                <td>
                    <?php if ($cancelled_count > 0): ?>
                        <span class="cancelled-badge"><?php echo $cancelled_count; ?> ORDER(S) CANCELLED</span>
                    <?php else: ?>
                        <span class="no-cancel">No orders were cancelled</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <?php if (!empty($cancelled_items) && is_array($cancelled_items)): ?>
        <h3 style="margin-top: 20px; font-size: 15px; color: #333;">Cancelled Meal Details</h3>
        <table class="items">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Meal</th>
                    <th>Menu Item</th>
                    <th>Option</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cancelled_items as $item): ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($item['order_date'])); ?></td>
                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($item['menu_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($item['menu_option_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $item['cancel_reason'] ?? ''))); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <div class="footer">
            <p>This is an automated notification from the BizOrder system.</p>
            <p>Generated at: <?php echo $discharge_time; ?></p>
        </div>
    </div>
</body>
</html>
