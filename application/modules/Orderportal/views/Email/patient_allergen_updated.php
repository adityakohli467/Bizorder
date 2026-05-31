<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; margin: 0; padding: 0; }
        .header { background-color: #fd7e14; color: #fff; padding: 15px 20px; border-radius: 6px 6px 0 0; }
        .header h2 { margin: 0; font-size: 18px; }
        .content { padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 6px 6px; }
        table.info { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.info th { background-color: #f8f9fa; padding: 10px 14px; border: 1px solid #ddd; text-align: left; font-size: 13px; width: 200px; }
        table.info td { padding: 10px 14px; border: 1px solid #ddd; font-size: 13px; }
        table.comparison { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.comparison th { background-color: #f8f9fa; padding: 10px 14px; border: 1px solid #ddd; text-align: left; font-size: 13px; }
        table.comparison td { padding: 10px 14px; border: 1px solid #ddd; font-size: 13px; }
        .old-value { color: #dc3545; text-decoration: line-through; }
        .new-value { color: #28a745; font-weight: bold; }
        .changed-badge { display: inline-block; background-color: #fd7e14; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .no-change { color: #6c757d; font-style: italic; }
        .footer { margin-top: 20px; font-size: 12px; color: #888; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>&#9888; Allergen / Dietary Restrictions Updated</h2>
    </div>
    <div class="content">
        <p style="margin-top: 0;">Allergen or dietary restrictions have been updated for a patient. Please review the changes below:</p>
        
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
                <th>Updated By</th>
                <td><?php echo htmlspecialchars($updated_by); ?></td>
            </tr>
            <tr>
                <th>Updated At</th>
                <td><?php echo $updated_at; ?></td>
            </tr>
        </table>
        
        <h3 style="margin-top: 20px; font-size: 15px; color: #333;">Changes Summary</h3>
        <table class="comparison">
            <thead>
                <tr>
                    <th>Field</th>
                    <th>Previous Value</th>
                    <th>New Value</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($allergies_changed): ?>
                <tr>
                    <td><strong>Allergies</strong> <span class="changed-badge">CHANGED</span></td>
                    <td class="old-value"><?php echo !empty($old_allergies) ? htmlspecialchars($old_allergies) : '<em>None</em>'; ?></td>
                    <td class="new-value"><?php echo !empty($new_allergies) ? htmlspecialchars($new_allergies) : '<em>None</em>'; ?></td>
                </tr>
                <?php else: ?>
                <tr>
                    <td><strong>Allergies</strong></td>
                    <td colspan="2" class="no-change">No change</td>
                </tr>
                <?php endif; ?>
                
                <?php if ($dietary_changed): ?>
                <tr>
                    <td><strong>Dietary Preferences</strong> <span class="changed-badge">CHANGED</span></td>
                    <td class="old-value"><?php echo !empty($old_dietary) ? htmlspecialchars($old_dietary) : '<em>None</em>'; ?></td>
                    <td class="new-value"><?php echo !empty($new_dietary) ? htmlspecialchars($new_dietary) : '<em>None</em>'; ?></td>
                </tr>
                <?php else: ?>
                <tr>
                    <td><strong>Dietary Preferences</strong></td>
                    <td colspan="2" class="no-change">No change</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>This is an automated notification from the BizOrder system.</p>
            <p>Generated at: <?php echo $updated_at; ?></p>
        </div>
    </div>
</body>
</html>
