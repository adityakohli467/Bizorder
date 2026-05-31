<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; margin: 0; padding: 0; }
        .header { background-color: #17a2b8; color: #fff; padding: 15px 20px; border-radius: 6px 6px 0 0; }
        .header h2 { margin: 0; font-size: 18px; }
        .content { padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 6px 6px; }
        table.info { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.info th { background-color: #f8f9fa; padding: 10px 14px; border: 1px solid #ddd; text-align: left; font-size: 13px; width: 200px; }
        table.info td { padding: 10px 14px; border: 1px solid #ddd; font-size: 13px; }
        .transfer-arrow { font-size: 18px; color: #17a2b8; font-weight: bold; }
        .footer { margin-top: 20px; font-size: 12px; color: #888; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>&#128260; Patient Suite Transfer</h2>
    </div>
    <div class="content">
        <p style="margin-top: 0;">A patient has been moved/shifted to a different suite. Please find the details below:</p>
        
        <table class="info">
            <tr>
                <th>Patient Name</th>
                <td><?php echo htmlspecialchars($patient_name); ?></td>
            </tr>
            <tr>
                <th>Transferred From</th>
                <td><strong><?php echo htmlspecialchars($from_suite); ?></strong> (<?php echo htmlspecialchars($from_floor); ?>)</td>
            </tr>
            <tr>
                <th>Transferred To</th>
                <td><strong><?php echo htmlspecialchars($to_suite); ?></strong> (<?php echo htmlspecialchars($to_floor); ?>)</td>
            </tr>
            <tr>
                <th>Orders Transferred</th>
                <td><?php echo $orders_transferred; ?> meal order(s) moved to the new suite</td>
            </tr>
            <tr>
                <th>Transfer Time</th>
                <td><?php echo $transfer_time; ?></td>
            </tr>
        </table>
        
        <div class="footer">
            <p>This is an automated notification from the BizOrder system.</p>
            <p>Generated at: <?php echo $transfer_time; ?></p>
        </div>
    </div>
</body>
</html>
