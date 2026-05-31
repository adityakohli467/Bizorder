<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; margin: 0; padding: 0; }
        .header { background-color: #28a745; color: #fff; padding: 15px 20px; border-radius: 6px 6px 0 0; }
        .header h2 { margin: 0; font-size: 18px; }
        .content { padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 6px 6px; }
        table.info { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.info th { background-color: #f8f9fa; padding: 10px 14px; border: 1px solid #ddd; text-align: left; font-size: 13px; width: 200px; }
        table.info td { padding: 10px 14px; border: 1px solid #ddd; font-size: 13px; }
        .footer { margin-top: 20px; font-size: 12px; color: #888; border-top: 1px solid #eee; padding-top: 10px; }
        .alert-badge { display: inline-block; background-color: #dc3545; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .no-allergy { color: #28a745; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h2>&#10004; New Patient Onboarded</h2>
    </div>
    <div class="content">
        <p style="margin-top: 0;">A new patient has been onboarded to the system. Please find the details below:</p>
        
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
                <th>Floor</th>
                <td><?php echo htmlspecialchars($floor_name); ?></td>
            </tr>
            <tr>
                <th>Onboard Date</th>
                <td><?php echo htmlspecialchars($onboard_date); ?></td>
            </tr>
            <tr>
                <th>Allergies</th>
                <td>
                    <?php if (!empty($allergies_list)): ?>
                        <span class="alert-badge">ALLERGIES REPORTED</span><br><br>
                        <?php echo htmlspecialchars($allergies_list); ?>
                    <?php else: ?>
                        <span class="no-allergy">No allergies reported</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Dietary Preferences</th>
                <td><?php echo !empty($dietary_list) ? htmlspecialchars($dietary_list) : 'Standard'; ?></td>
            </tr>
        </table>
        
        <div class="footer">
            <p>This is an automated notification from the BizOrder system.</p>
            <p>Generated at: <?php echo $generated_at; ?></p>
        </div>
    </div>
</body>
</html>
