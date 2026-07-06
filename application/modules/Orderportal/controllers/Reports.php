<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reports extends MY_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->model('common_model');
        // Check if user is logged in
        !$this->ion_auth->logged_in() ? redirect('auth/login', 'refresh') : '';
        $this->restrictHospitalRole();
    }
    
    /**
     * Main reports index page
     * Shows order reports with filters
     */
    public function index()
{
    $data = [];

    $data['page_title'] = 'Order Reports';
    $data['pagefor']    = 'reports';

   
    $from_date = $this->input->post('from_date');
    $to_date   = $this->input->post('to_date');

    // Validate & set default dates (last 7 days)
    if (!isset($from_date) || empty($from_date)) {
        $from_date = date('Y-m-d', strtotime('-7 days'));
    }

    if (!isset($to_date) || empty($to_date)) {
        $to_date = date('Y-m-d');
    }

    $data['from_date'] = $from_date;
    $data['to_date']   = $to_date;

   
    $orders = $this->getOrdersReport($from_date, $to_date);
    $data['orders'] = (isset($orders) && is_array($orders)) ? $orders : [];

    
    $data['total_orders'] = count($data['orders']);
    $data['total_items']  = 0;

    if (!empty($data['orders'])) {
        foreach ($data['orders'] as $order) {
            if (isset($order['item_count'])) {
                $data['total_items'] += (int) $order['item_count'];
            }
        }
    }

    // Beds serviced per day
    $beds_per_day = $this->getBedsServicedPerDay($from_date, $to_date);
    $data['beds_per_day'] = (isset($beds_per_day) && is_array($beds_per_day)) ? $beds_per_day : [];

    // Total beds serviced in month
    $total_beds_month = $this->getTotalBedsServicedInMonth($from_date, $to_date);
    $data['total_beds_month'] = isset($total_beds_month) ? (int) $total_beds_month : 0;

   
    $this->load->view('general/header', $data);
    $this->load->view('Orderportal/Reports/index', $data);
    $this->load->view('general/footer', $data);
}

    
    /**
     * Get beds/suites serviced per day
     */
    private function getBedsServicedPerDay($from_date, $to_date) {
        // Count distinct (bed_id, patient_id) pairs per day.
        // Uses suite_order_details.patient_id to correctly count multiple patients
        // per bed on the same day (e.g. Patient A discharged after breakfast,
        // Patient B checked in for lunch/dinner = 2 beds serviced).
        // Falls back to opo.patient_id, then counts bed as 1 if no patient info.
        $sql = "SELECT 
                    o.date as order_date,
                    COUNT(DISTINCT opo.bed_id, COALESCE(sd.patient_id, NULLIF(opo.patient_id, 0), opo.bed_id)) as beds_count
                FROM orders o
                INNER JOIN orders_to_patient_options opo ON opo.order_id = o.order_id
                INNER JOIN suites s ON s.id = opo.bed_id
                LEFT JOIN suite_order_details sd ON sd.id = opo.suite_order_detail_id
                WHERE o.date >= ? AND o.date <= ?
                AND o.status != 0
                AND s.is_deleted = 0
                AND s.status = 1
                AND (opo.is_cancelled = 0 OR opo.is_cancelled IS NULL)
                GROUP BY o.date
                ORDER BY o.date ASC";
        
        $query = $this->tenantDb->query($sql, [$from_date, $to_date]);
        return $query->result_array();
    }
    
    /**
     * Get total beds serviced in a month
     * Sums all beds from each day in the current month (month of to_date)
     */
    private function getTotalBedsServicedInMonth($from_date, $to_date) {
        // Get the current month (month of to_date)
        $month_start = date('Y-m-01', strtotime($to_date));
        $month_end = date('Y-m-t', strtotime($to_date));
        
        // Get beds per day for the current month
        // Uses suite_order_details.patient_id for accurate patient-per-bed counting.
        $sql = "SELECT 
                    o.date as order_date,
                    COUNT(DISTINCT opo.bed_id, COALESCE(sd.patient_id, NULLIF(opo.patient_id, 0), opo.bed_id)) as beds_count
                FROM orders o
                INNER JOIN orders_to_patient_options opo ON opo.order_id = o.order_id
                INNER JOIN suites s ON s.id = opo.bed_id
                LEFT JOIN suite_order_details sd ON sd.id = opo.suite_order_detail_id
                WHERE o.date >= ? AND o.date <= ?
                AND o.status != 0
                AND s.is_deleted = 0
                AND s.status = 1
                AND (opo.is_cancelled = 0 OR opo.is_cancelled IS NULL)
                GROUP BY o.date
                ORDER BY o.date ASC";
        
        $query = $this->tenantDb->query($sql, [$month_start, $month_end]);
        $beds_per_day = $query->result_array();
        
        // Sum all beds from each day
        $total = 0;
        foreach ($beds_per_day as $day) {
            $total += (int)$day['beds_count'];
        }
        
        return $total;
    }
    
    /**
     * Get orders report data
     */
    private function getOrdersReport($from_date, $to_date) {
        $sql = "SELECT 
                    o.order_id,
                    o.date as order_date,
                    o.buttonType,
                    o.status,
                    o.workflow_status,
                    o.is_floor_consolidated,
                    o.date as created_at,
                    COUNT(CASE WHEN (opo.is_cancelled = 0 OR opo.is_cancelled IS NULL) THEN opo.id ELSE NULL END) as item_count,
                    COUNT(CASE WHEN opo.is_cancelled = 1 THEN opo.id ELSE NULL END) as cancelled_item_count,
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                    u.username as created_by_username
                FROM orders o
                LEFT JOIN orders_to_patient_options opo ON opo.order_id = o.order_id
                LEFT JOIN Global_users u ON u.id = o.added_by
                WHERE o.date >= ? AND o.date <= ?
                GROUP BY o.order_id
                ORDER BY o.order_id DESC";
        
        $query = $this->tenantDb->query($sql, [$from_date, $to_date]);
        return $query->result_array();
    }
    
    /**
     * Order detail report
     */
    public function orderDetail($order_id) {
        $data['page_title'] = 'Order Detail Report';
        $data['pagefor'] = 'reports';
        
        // Get order details with creator information
        $sql = "SELECT o.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                       u.username as created_by_username
                FROM orders o
                LEFT JOIN Global_users u ON u.id = o.added_by
                WHERE o.order_id = ?";
        
        $query = $this->tenantDb->query($sql, [$order_id]);
        $order = $query->row_array();
        
        if (empty($order)) {
            show_404();
            return;
        }
        
        $data['order'] = $order;
        
        // Get order items with suite/bed and menu information
        // ✅ PATIENT ID FIX: JOIN on patient_id to get correct patient at order time
        // ✅ SOFT DELETE: Exclude cancelled items but show them separately
        $sql = "SELECT opo.*,
                       s.bed_no,
                       s.floor,
                       s.id as suite_id,
                       p.name as patient_name,
                       p.allergies as patient_allergies,
                       md.name as menu_name,
                       md.description as menu_description,
                       fc.name as category_name
                FROM orders_to_patient_options opo
                LEFT JOIN suites s ON s.id = opo.bed_id
                LEFT JOIN people p ON p.id = opo.patient_id
                LEFT JOIN menuDetails md ON md.id = opo.menu_id
                LEFT JOIN foodmenuconfig fc ON fc.id = opo.category_id AND fc.listtype = 'category'
                WHERE opo.order_id = ?
                AND (opo.is_cancelled = 0 OR opo.is_cancelled IS NULL)
                ORDER BY s.floor, s.bed_no, opo.id";
        
        $query = $this->tenantDb->query($sql, [$order_id]);
        $data['order_items'] = $query->result_array();
        
        // Load views
        $this->load->view('general/header', $data);
        $this->load->view('Orderportal/Reports/order_detail', $data);
        $this->load->view('general/footer', $data);
    }
    
    /**
     * Export orders to Excel
     */
    public function exportOrders() {
        $from_date = $this->input->post('from_date') ?: date('Y-m-d', strtotime('-7 days'));
        $to_date = $this->input->post('to_date') ?: date('Y-m-d');
        
        $orders = $this->getOrdersReport($from_date, $to_date);
        
        // Prepare CSV data
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="order_report_' . date('Y-m-d_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, [
            'Order ID',
            'Order Date',
            'Status',
            'Workflow Status',
            'Type',
            'Item Count',
            'Created By',
            'Created At'
        ]);
        
        // CSV Data
        foreach ($orders as $order) {
            fputcsv($output, [
                $order['order_id'],
                $order['order_date'],
                $order['status'],
                $order['workflow_status'] ?: 'N/A',
                $order['is_floor_consolidated'] == 1 ? 'Floor Consolidated' : 'Legacy',
                $order['item_count'],
                $order['created_by_name'] ?: $order['created_by_username'],
                $order['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export beds serviced per day to Excel
     */
    public function exportBedsServiced() {
        $from_date = $this->input->post('from_date') ?: date('Y-m-d', strtotime('-7 days'));
        $to_date = $this->input->post('to_date') ?: date('Y-m-d');
        
        $beds_per_day = $this->getBedsServicedPerDay($from_date, $to_date);
        
        // Prepare CSV data
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="beds_serviced_report_' . date('Y-m-d_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Title
        fputcsv($output, ['Beds (Suites) Serviced Per Day Report']);
        fputcsv($output, ['Date Range: ' . date('d M Y', strtotime($from_date)) . ' to ' . date('d M Y', strtotime($to_date))]);
        fputcsv($output, []); // Empty row
        
        // CSV Headers
        fputcsv($output, [
            'Date',
            'Day of Week',
            'Beds Serviced'
        ]);
        
        // CSV Data
        $total_beds = 0;
        foreach ($beds_per_day as $day) {
            $total_beds += $day['beds_count'];
            fputcsv($output, [
                date('d M Y', strtotime($day['order_date'])),
                date('l', strtotime($day['order_date'])),
                $day['beds_count']
            ]);
        }
        
        // Add summary
        fputcsv($output, []); // Empty row
        fputcsv($output, ['Total Days', count($beds_per_day)]);
        fputcsv($output, ['Total Beds Serviced', $total_beds]);
        fputcsv($output, ['Average Beds Per Day', count($beds_per_day) > 0 ? round($total_beds / count($beds_per_day), 2) : 0]);
        
        fclose($output);
        exit;
    }
    
    /**
     * Export Patient Report to Excel
     */
    public function exportPatientReport() {
        $from_date = $this->input->post('from_date') ?: date('Y-m-d', strtotime('-7 days'));
        $to_date = $this->input->post('to_date') ?: date('Y-m-d');
        
        // Get patient data with onboarding and discharge dates
        // Note: People table has suite_number and floor_number to link with suites
        $sql = "SELECT 
                p.id as patient_id,
                p.name as patient_name,
                p.suite_number,
                p.floor_number,
                p.allergies,
                p.dietary_preferences,
                p.special_instructions,
                p.date_onboarded as onboarded_date,
                p.date_of_discharge as discharge_date,
                p.status as patient_status,
                s.id as suite_id,
                s.bed_no as suite_number,
                s.status as suite_status,
                f.name as floor_name
            FROM people p
            LEFT JOIN suites s ON s.id = p.suite_number AND s.floor = p.floor_number AND s.is_deleted = 0
            LEFT JOIN foodmenuconfig f ON f.id = p.floor_number AND f.listtype = 'floor' AND f.is_deleted = 0
            WHERE p.id IS NOT NULL
            AND (
                (p.date_onboarded >= ? AND p.date_onboarded <= ?)
                OR (p.date_of_discharge >= ? AND p.date_of_discharge <= ?)
                OR (p.date_onboarded <= ? AND (p.date_of_discharge >= ? OR p.date_of_discharge IS NULL))
            )
            ORDER BY p.date_onboarded DESC, p.suite_number ASC";
        
        $query = $this->tenantDb->query($sql, [
            $from_date, $to_date,
            $from_date, $to_date,
            $from_date, $to_date
        ]);
        $patients = $query->result_array();
        
        // Prepare CSV data
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="patient_report_' . date('Y-m-d_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Title
        fputcsv($output, ['Patient Report']);
        fputcsv($output, ['Date Range: ' . date('d M Y', strtotime($from_date)) . ' to ' . date('d M Y', strtotime($to_date))]);
        fputcsv($output, []); // Empty row
        
        // CSV Headers
        fputcsv($output, [
            'Suite Number',
            'Floor',
            'Patient Name',
            'Date Onboarded',
            'Date Discharged',
            'Status',
           
        ]);
        
        // CSV Data
        $total_active = 0;
        $total_discharged = 0;
        
        foreach ($patients as $patient) {
            $status = 'Unknown';
            if ($patient['patient_status'] == 1 || $patient['patient_status'] === '1') {
                $status = $patient['discharge_date'] ? 'Discharged' : 'Active';
                if ($status == 'Active') $total_active++;
                if ($status == 'Discharged') $total_discharged++;
            } else {
                $status = 'Inactive';
            }
            
            fputcsv($output, [
                $patient['suite_number'] ?: 'N/A',
                $patient['floor_name'] ?: 'N/A',
                $patient['patient_name'] ?: 'No Patient Assigned',
                $patient['onboarded_date'] ? date('d M Y', strtotime($patient['onboarded_date'])) : 'N/A',
                $patient['discharge_date'] ? date('d M Y', strtotime($patient['discharge_date'])) : 'N/A',
                $status,
               
            ]);
        }
        
        // Add summary
        fputcsv($output, []); // Empty row
        fputcsv($output, ['Summary']);
        fputcsv($output, ['Total Patients', count($patients)]);
        fputcsv($output, ['Active Patients', $total_active]);
        fputcsv($output, ['Discharged Patients', $total_discharged]);
        
        fclose($output);
        exit;
    }
    
    /**
     * Check-ins & Active Customers Report
     *
     * Lists every check-in (one row per person) plus all currently active customers
     * for the selected date range. A check-in is shown even if the customer checked
     * out later the same day. Because the report is built directly from the people
     * table (one row per person), a suite that is checked-out and then re-occupied
     * on the same day produces TWO rows - the new occupant never overrides the
     * previous occupant's record.
     */
    public function checkinReport() {
        $from_date = $this->input->post('from_date') ?: $this->input->get('from_date');
        $to_date   = $this->input->post('to_date') ?: $this->input->get('to_date');

        if (empty($from_date)) {
            $from_date = date('Y-m-d');
        }
        if (empty($to_date)) {
            $to_date = date('Y-m-d');
        }

        $records = $this->getCheckinReport($from_date, $to_date);
        $records = is_array($records) ? $records : [];

        $total_active    = 0;
        $total_checkedout = 0;
        foreach ($records as $row) {
            if (!empty($row['date_of_discharge'])) {
                $total_checkedout++;
            } else {
                $total_active++;
            }
        }

        $data['records']          = $records;
        $data['from_date']        = $from_date;
        $data['to_date']          = $to_date;
        $data['total_records']    = count($records);
        $data['total_active']     = $total_active;
        $data['total_checkedout'] = $total_checkedout;
        $data['page_title']       = 'Check-ins & Active Customers Report';
        $data['pagefor']          = 'reports';

        $this->load->view('general/header', $data);
        $this->load->view('Orderportal/Reports/checkin_report', $data);
        $this->load->view('general/footer', $data);
    }

    /**
     * Build the check-ins / active customers dataset.
     *
     * One row per person from the people table, filtered by check-in date
     * (date_onboarded) within the selected range. This naturally returns
     * multiple rows for a suite that was re-occupied on the same day.
     */
    private function getCheckinReport($from_date, $to_date) {
        $sql = "SELECT
                    p.id                 AS patient_id,
                    p.name               AS patient_name,
                    p.date_onboarded     AS date_onboarded,
                    p.time_onboarded     AS time_onboarded,
                    p.date_of_discharge  AS date_of_discharge,
                    p.time_discharged    AS time_discharged,
                    p.status             AS patient_status,
                    s.bed_no             AS suite_number,
                    fmc.name             AS floor_name
                FROM people p
                LEFT JOIN suites s ON s.id = p.suite_number AND s.is_deleted = 0
                LEFT JOIN foodmenuconfig fmc ON fmc.id = p.floor_number AND fmc.listtype = 'floor'
                WHERE p.date_onboarded IS NOT NULL
                  AND p.date_onboarded >= ?
                  AND p.date_onboarded <= ?
                ORDER BY p.date_onboarded ASC, s.bed_no ASC, p.time_discharged ASC, p.id ASC";

        $query = $this->tenantDb->query($sql, [$from_date, $to_date]);
        return $query->result_array();
    }

    /**
     * Export the Check-ins & Active Customers report to CSV.
     */
    public function exportCheckinReport() {
        $from_date = $this->input->post('from_date') ?: date('Y-m-d');
        $to_date   = $this->input->post('to_date') ?: date('Y-m-d');

        $records = $this->getCheckinReport($from_date, $to_date);
        $records = is_array($records) ? $records : [];

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="checkin_report_' . date('Y-m-d_His') . '.csv"');

        $output = fopen('php://output', 'w');

        // Title
        fputcsv($output, ['Check-ins & Active Customers Report']);
        fputcsv($output, ['Date Range: ' . date('d M Y', strtotime($from_date)) . ' to ' . date('d M Y', strtotime($to_date))]);
        fputcsv($output, []);

        // Headers (Date, Customer Name, Check In Time, Check Out Time, Suite No)
        fputcsv($output, ['Date', 'Customer Name', 'Check In Time', 'Check Out Time', 'Suite No']);

        foreach ($records as $row) {
            $checkin = '';
            if (!empty($row['time_onboarded']) && $row['time_onboarded'] != '0000-00-00 00:00:00') {
                $checkin = date('d M Y h:i A', strtotime($row['time_onboarded']));
            } elseif (!empty($row['date_onboarded'])) {
                $checkin = date('d M Y', strtotime($row['date_onboarded']));
            } else {
                $checkin = 'N/A';
            }

            $checkout = '';
            if (!empty($row['time_discharged']) && $row['time_discharged'] != '0000-00-00 00:00:00') {
                // Guard against legacy bad data where the stored check-out time is
                // earlier than the check-in (e.g. recorded in a different timezone).
                if (!empty($row['time_onboarded'])
                    && strtotime($row['time_discharged']) < strtotime($row['time_onboarded'])) {
                    $checkout = (!empty($row['date_of_discharge']) && $row['date_of_discharge'] != '0000-00-00')
                        ? date('d M Y', strtotime($row['date_of_discharge']))
                        : date('d M Y', strtotime($row['time_discharged']));
                } else {
                    $checkout = date('d M Y h:i A', strtotime($row['time_discharged']));
                }
            } elseif (!empty($row['date_of_discharge']) && $row['date_of_discharge'] != '0000-00-00') {
                $checkout = date('d M Y', strtotime($row['date_of_discharge']));
            } else {
                $checkout = 'Still Checked In';
            }

            fputcsv($output, [
                !empty($row['date_onboarded']) ? date('d M Y', strtotime($row['date_onboarded'])) : 'N/A',
                $row['patient_name'] ?: 'N/A',
                $checkin,
                $checkout,
                $row['suite_number'] ?: 'N/A'
            ]);
        }

        fclose($output);
        exit;
    }

    // ============================================================
    //  ADMIN REPORTS DASHBOARD
    //  Consolidated landing page for admin users with quick stats,
    //  a date-range filter, range stats and links to all reports.
    // ============================================================

    /**
     * Admin Reports Dashboard (landing page for admin role).
     */
    public function adminDashboard() {
        $today = australia_date_only();

        // Date range filter (default = current month)
        $from_date = $this->input->post('from_date') ?: $this->input->get('from_date');
        $to_date   = $this->input->post('to_date')   ?: $this->input->get('to_date');

        if (empty($from_date)) {
            $from_date = date('Y-m-01', strtotime($today)); // first day of current month
        }
        if (empty($to_date)) {
            $to_date = $today;                               // default up to today (not future)
        }

        // From Date cannot be later than To Date - swap if reversed.
        if (strtotime($from_date) > strtotime($to_date)) {
            $tmp = $from_date; $from_date = $to_date; $to_date = $tmp;
        }

        // ---- Quick Stats (Today) ----
        $data['today_onboarded']   = $this->countOnboardedInRange($today, $today);
        $data['today_discharged']  = $this->countDischargedInRange($today, $today);
        $data['today_active']      = $this->countActiveOnDate($today);
        $data['today_food_orders'] = ''; // intentionally blank for now

        // ---- Stats for Selected Range ----
        $rangeDaily = $this->getActivePatientsByDay($from_date, $to_date);
        $rangeActiveSum = 0;
        $rangeCats = [];
        $rangeOnboardSpark = [];
        $rangeDischargeSpark = [];
        $rangeActiveSpark = [];
        foreach ($rangeDaily as $d) {
            $rangeCats[]           = $d['date'];
            $rangeOnboardSpark[]   = (int) $d['onboarded'];
            $rangeDischargeSpark[] = (int) $d['discharged'];
            $rangeActiveSpark[]    = (int) $d['active'];
            $rangeActiveSum       += (int) $d['active'];
        }

        $data['range_onboarded']   = $this->countOnboardedInRange($from_date, $to_date);
        $data['range_discharged']  = $this->countDischargedInRange($from_date, $to_date);
        $data['range_active']      = $rangeActiveSum;
        $data['range_food_orders'] = ''; // intentionally blank for now

        // ---- Sparkline trend series ----
        // Today cards: last 14 days ending today.
        $sparkFrom  = date('Y-m-d', strtotime($today . ' -13 days'));
        $todayDaily = $this->getActivePatientsByDay($sparkFrom, $today);
        $spCats = []; $spOnboard = []; $spDischarge = []; $spActive = [];
        foreach ($todayDaily as $d) {
            $spCats[]      = $d['date'];
            $spOnboard[]   = (int) $d['onboarded'];
            $spDischarge[] = (int) $d['discharged'];
            $spActive[]    = (int) $d['active'];
        }
        $spFood = $this->buildFoodSpark($spCats, $sparkFrom, $today);

        $data['spark_today'] = [
            'onboard'   => $spOnboard,
            'discharge' => $spDischarge,
            'active'    => $spActive,
            'food'      => $spFood,
        ];
        $data['spark_range'] = [
            'onboard'   => $rangeOnboardSpark,
            'discharge' => $rangeDischargeSpark,
            'active'    => $rangeActiveSpark,
            'food'      => $this->buildFoodSpark($rangeCats, $from_date, $to_date),
        ];

        $data['from_date']  = $from_date;
        $data['to_date']    = $to_date;
        $data['today']      = $today;
        $data['page_title'] = 'Order Reports';
        $data['pagefor']    = 'reports';

        $this->load->view('general/header', $data);
        $this->load->view('Orderportal/Reports/admin_dashboard', $data);
        $this->load->view('general/footer', $data);
    }

    // ---------------- Stat helper queries ----------------

    /**
     * Count distinct patients onboarded within a date range.
     * De-dupes by (name + date_onboarded).
     */
    private function countOnboardedInRange($from_date, $to_date) {
        $sql = "SELECT COUNT(*) AS cnt FROM (
                    SELECT p.name, p.date_onboarded
                    FROM people p
                    WHERE p.date_onboarded IS NOT NULL
                      AND p.date_onboarded <> '0000-00-00'
                      AND p.date_onboarded >= ?
                      AND p.date_onboarded <= ?
                    GROUP BY p.name, p.date_onboarded
                ) t";
        $row = $this->tenantDb->query($sql, [$from_date, $to_date])->row_array();
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Count distinct patients discharged within a date range.
     * De-dupes by (name + date_of_discharge).
     */
    private function countDischargedInRange($from_date, $to_date) {
        $sql = "SELECT COUNT(*) AS cnt FROM (
                    SELECT p.name, p.date_of_discharge
                    FROM people p
                    WHERE p.date_of_discharge IS NOT NULL
                      AND p.date_of_discharge <> '0000-00-00'
                      AND p.date_of_discharge >= ?
                      AND p.date_of_discharge <= ?
                    GROUP BY p.name, p.date_of_discharge
                ) t";
        $row = $this->tenantDb->query($sql, [$from_date, $to_date])->row_array();
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Count distinct patients active on a specific date.
     * Active = onboarded on/before date AND (not discharged OR discharged on/after date).
     * De-dupes by (name + date_onboarded).
     */
    private function countActiveOnDate($date) {
        $sql = "SELECT COUNT(*) AS cnt FROM (
                    SELECT p.name, p.date_onboarded
                    FROM people p
                    WHERE p.date_onboarded IS NOT NULL
                      AND p.date_onboarded <> '0000-00-00'
                      AND p.date_onboarded <= ?
                      AND (
                            p.date_of_discharge IS NULL
                            OR p.date_of_discharge = '0000-00-00'
                            OR p.date_of_discharge >= ?
                      )
                    GROUP BY p.name, p.date_onboarded
                ) t";
        $row = $this->tenantDb->query($sql, [$date, $date])->row_array();
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Fetch people that could be active on any day within the range.
     */
    private function fetchPeopleForActiveCalc($from_date, $to_date) {
        $sql = "SELECT p.id, p.name, p.date_onboarded, p.date_of_discharge
                FROM people p
                WHERE p.date_onboarded IS NOT NULL
                  AND p.date_onboarded <> '0000-00-00'
                  AND p.date_onboarded <= ?
                  AND (
                        p.date_of_discharge IS NULL
                        OR p.date_of_discharge = '0000-00-00'
                        OR p.date_of_discharge >= ?
                  )
                ORDER BY p.date_onboarded ASC, p.name ASC";
        $query = $this->tenantDb->query($sql, [$to_date, $from_date]);
        return $query->result_array();
    }

    /**
     * Build a per-day active patients breakdown for the range.
     * Each entry: date, active (count), names[], onboarded, discharged.
     * De-dupes by (name + date_onboarded).
     */
    private function getActivePatientsByDay($from_date, $to_date) {
        $people = $this->fetchPeopleForActiveCalc($from_date, $to_date);

        // Never project active patients into the future - cap at today.
        $endTs = min(strtotime($to_date), strtotime(australia_date_only()));

        $result = [];
        for ($ts = strtotime($from_date); $ts <= $endTs; $ts += 86400) {
            $day = date('Y-m-d', $ts);

            $activeNames = [];
            $seenActive  = [];
            $onboarded   = 0; $seenOn  = [];
            $discharged  = 0; $seenDis = [];

            foreach ($people as $p) {
                $on     = $p['date_onboarded'];
                $dis    = $p['date_of_discharge'];
                $hasDis = !empty($dis) && $dis !== '0000-00-00';
                $key    = $p['name'] . '|' . $on;

                if ($on <= $day && (!$hasDis || $dis >= $day)) {
                    if (!isset($seenActive[$key])) {
                        $seenActive[$key] = true;
                        $activeNames[] = $p['name'];
                    }
                }
                if ($on === $day && !isset($seenOn[$key])) {
                    $seenOn[$key] = true;
                    $onboarded++;
                }
                if ($hasDis && $dis === $day) {
                    $disKey = $p['name'] . '|' . $dis;
                    if (!isset($seenDis[$disKey])) {
                        $seenDis[$disKey] = true;
                        $discharged++;
                    }
                }
            }

            $result[] = [
                'date'       => $day,
                'active'     => count($activeNames),
                'names'      => $activeNames,
                'onboarded'  => $onboarded,
                'discharged' => $discharged,
            ];
        }
        return $result;
    }

    /**
     * Sum of daily active patient counts across the range (active patient-days).
     */
    private function countActivePatientDays($from_date, $to_date) {
        $total = 0;
        foreach ($this->getActivePatientsByDay($from_date, $to_date) as $d) {
            $total += (int) $d['active'];
        }
        return $total;
    }

    /**
     * Per-day count of distinct patients who placed a (non-cancelled) food order.
     * Counts by patient so two patients in the same suite on the same day
     * (one discharged, one checked in) yield two counts.
     */
    private function getFoodOrdersByDay($from_date, $to_date) {
        $sql = "SELECT
                    o.date AS order_date,
                    COUNT(DISTINCT COALESCE(sd.patient_id, NULLIF(opo.patient_id, 0), CONCAT('bed-', opo.bed_id))) AS patient_count
                FROM orders o
                INNER JOIN orders_to_patient_options opo ON opo.order_id = o.order_id
                LEFT JOIN suite_order_details sd ON sd.id = opo.suite_order_detail_id
                WHERE o.date >= ? AND o.date <= ?
                  AND o.status != 0
                  AND (opo.is_cancelled = 0 OR opo.is_cancelled IS NULL)
                GROUP BY o.date
                ORDER BY o.date ASC";
        $query = $this->tenantDb->query($sql, [$from_date, $to_date]);
        return $query->result_array();
    }

    /**
     * Build a per-day food-order series aligned to the given ordered date list.
     */
    private function buildFoodSpark($cats, $from_date, $to_date) {
        $map = [];
        foreach ($this->getFoodOrdersByDay($from_date, $to_date) as $fr) {
            $map[$fr['order_date']] = (int) $fr['patient_count'];
        }
        $series = [];
        foreach ($cats as $day) {
            $series[] = $map[$day] ?? 0;
        }
        return $series;
    }

    /**
     * Per-day check-in and check-out counts (de-duped by name + date).
     */
    private function getCheckinCheckoutByDay($from_date, $to_date) {
        $ciSql = "SELECT t.d AS d, COUNT(*) AS cnt FROM (
                      SELECT p.name AS n, p.date_onboarded AS d
                      FROM people p
                      WHERE p.date_onboarded >= ? AND p.date_onboarded <= ?
                        AND p.date_onboarded IS NOT NULL AND p.date_onboarded <> '0000-00-00'
                      GROUP BY p.name, p.date_onboarded
                  ) t GROUP BY t.d";
        $ciRows = $this->tenantDb->query($ciSql, [$from_date, $to_date])->result_array();
        $ciMap = [];
        foreach ($ciRows as $rw) { $ciMap[$rw['d']] = (int) $rw['cnt']; }

        $coSql = "SELECT t.d AS d, COUNT(*) AS cnt FROM (
                      SELECT p.name AS n, p.date_of_discharge AS d
                      FROM people p
                      WHERE p.date_of_discharge >= ? AND p.date_of_discharge <= ?
                        AND p.date_of_discharge IS NOT NULL AND p.date_of_discharge <> '0000-00-00'
                      GROUP BY p.name, p.date_of_discharge
                  ) t GROUP BY t.d";
        $coRows = $this->tenantDb->query($coSql, [$from_date, $to_date])->result_array();
        $coMap = [];
        foreach ($coRows as $rw) { $coMap[$rw['d']] = (int) $rw['cnt']; }

        // Never list future days - cap at today.
        $endTs = min(strtotime($to_date), strtotime(australia_date_only()));

        $result = [];
        for ($ts = strtotime($from_date); $ts <= $endTs; $ts += 86400) {
            $day = date('Y-m-d', $ts);
            $result[] = [
                'date'       => $day,
                'check_ins'  => isset($ciMap[$day]) ? $ciMap[$day] : 0,
                'check_outs' => isset($coMap[$day]) ? $coMap[$day] : 0,
            ];
        }
        return $result;
    }

    /**
     * Detailed patient list with check-in / check-out details for the range.
     */
    private function getPatientsCheckinCheckout($from_date, $to_date) {
        $sql = "SELECT
                    p.id,
                    p.name              AS patient_name,
                    p.date_onboarded,
                    p.time_onboarded,
                    p.date_of_discharge,
                    p.time_discharged,
                    p.status            AS patient_status,
                    s.bed_no            AS suite_number,
                    fmc.name            AS floor_name
                FROM people p
                LEFT JOIN suites s ON s.id = p.suite_number AND s.is_deleted = 0
                LEFT JOIN foodmenuconfig fmc ON fmc.id = p.floor_number AND fmc.listtype = 'floor'
                WHERE p.date_onboarded IS NOT NULL AND p.date_onboarded <> '0000-00-00'
                  AND (
                        (p.date_onboarded >= ? AND p.date_onboarded <= ?)
                        OR (p.date_of_discharge >= ? AND p.date_of_discharge <= ?)
                  )
                ORDER BY p.date_onboarded ASC, s.bed_no ASC, p.id ASC";
        $query = $this->tenantDb->query($sql, [$from_date, $to_date, $from_date, $to_date]);
        return $query->result_array();
    }

    /**
     * Format a people row into display-ready check-in/out fields.
     * Guards against legacy bad data (check-out earlier than check-in).
     */
    private function formatCheckinCheckoutRow($row) {
        $checkinTs = null;
        $checkin   = 'N/A';
        if (!empty($row['time_onboarded']) && $row['time_onboarded'] != '0000-00-00 00:00:00') {
            $checkin   = date('d M Y h:i A', strtotime($row['time_onboarded']));
            $checkinTs = strtotime($row['time_onboarded']);
        } elseif (!empty($row['date_onboarded']) && $row['date_onboarded'] != '0000-00-00') {
            $checkin   = date('d M Y', strtotime($row['date_onboarded']));
            $checkinTs = strtotime($row['date_onboarded']);
        }

        $hasDischargeDate = !empty($row['date_of_discharge']) && $row['date_of_discharge'] != '0000-00-00';
        $checkout   = 'Still Checked In';
        $checkoutTs = null;
        if (!empty($row['time_discharged']) && $row['time_discharged'] != '0000-00-00 00:00:00') {
            if (!empty($row['time_onboarded']) && strtotime($row['time_discharged']) < strtotime($row['time_onboarded'])) {
                // Legacy timezone bug - fall back to date only.
                $fallback   = $hasDischargeDate ? $row['date_of_discharge'] : $row['time_discharged'];
                $checkout   = date('d M Y', strtotime($fallback));
                $checkoutTs = strtotime($fallback);
            } else {
                $checkout   = date('d M Y h:i A', strtotime($row['time_discharged']));
                $checkoutTs = strtotime($row['time_discharged']);
            }
        } elseif ($hasDischargeDate) {
            $checkout   = date('d M Y', strtotime($row['date_of_discharge']));
            $checkoutTs = strtotime($row['date_of_discharge']);
        }

        $status = $hasDischargeDate ? 'Discharged' : 'Active';

        if ($checkoutTs && $checkinTs && $checkoutTs >= $checkinTs) {
            $duration = $this->formatDuration($checkoutTs - $checkinTs);
        } elseif (!$checkoutTs) {
            $duration = 'Ongoing';
        } else {
            $duration = 'N/A';
        }

        return [
            'checkin'  => $checkin,
            'checkout' => $checkout,
            'status'   => $status,
            'duration' => $duration,
        ];
    }

    /**
     * Format a duration (seconds) into a whole number of days.
     */
    private function formatDuration($seconds) {
        $seconds = max(0, (int) $seconds);
        $days = (int) round($seconds / 86400);
        return $days . ' ' . ($days === 1 ? 'day' : 'days');
    }

    /**
     * Report metadata (generated timestamp + generated-by user).
     */
    private function getReportMeta() {
        $user = $this->ion_auth->user()->row();
        $name = '';
        if ($user) {
            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            if ($name === '') { $name = $user->username ?? 'Unknown'; }
        }
        return [
            'generated_at' => australia_date('d M Y h:i A'),
            'generated_by' => $name !== '' ? $name : 'Unknown',
        ];
    }

    // ---------------- Report pages ----------------

    /**
     * Resolve the from/to date range from the request, defaulting to current month.
     */
    private function resolveReportRange() {
        $today     = australia_date_only();
        $from_date = $this->input->post('from_date') ?: $this->input->get('from_date') ?: date('Y-m-01', strtotime($today));
        $to_date   = $this->input->post('to_date')   ?: $this->input->get('to_date')   ?: $today;
        if (strtotime($from_date) > strtotime($to_date)) {
            $tmp = $from_date; $from_date = $to_date; $to_date = $tmp;
        }
        return [$from_date, $to_date];
    }

    /** Report 1: Total Number of Active Patients (daily breakdown). */
    public function activePatientsReport() {
        list($from_date, $to_date) = $this->resolveReportRange();
        $data['days']       = $this->getActivePatientsByDay($from_date, $to_date);
        $data['total_days'] = 0;
        foreach ($data['days'] as $d) { $data['total_days'] += (int) $d['active']; }
        $data['from_date']  = $from_date;
        $data['to_date']    = $to_date;
        $data['page_title'] = 'Total Number of Active Patients';
        $data['pagefor']    = 'reports';

        $this->load->view('general/header', $data);
        $this->load->view('Orderportal/Reports/active_patients_report', $data);
        $this->load->view('general/footer', $data);
    }

    /** Report 2: Total Number of Patients Ordered Food (per day). */
    public function foodOrdersReport() {
        list($from_date, $to_date) = $this->resolveReportRange();
        $data['rows']       = $this->getFoodOrdersByDay($from_date, $to_date);
        $data['total']      = 0;
        foreach ($data['rows'] as $r) { $data['total'] += (int) $r['patient_count']; }
        $data['from_date']  = $from_date;
        $data['to_date']    = $to_date;
        $data['page_title'] = 'Total Number of Patients Ordered Food';
        $data['pagefor']    = 'reports';

        $this->load->view('general/header', $data);
        $this->load->view('Orderportal/Reports/food_orders_report', $data);
        $this->load->view('general/footer', $data);
    }

    /** Report 3: Total Number of Check-ins & Check-outs in the Day. */
    public function checkinCheckoutCountReport() {
        list($from_date, $to_date) = $this->resolveReportRange();
        $data['rows']            = $this->getCheckinCheckoutByDay($from_date, $to_date);
        $data['total_checkins']  = 0;
        $data['total_checkouts'] = 0;
        foreach ($data['rows'] as $r) {
            $data['total_checkins']  += (int) $r['check_ins'];
            $data['total_checkouts'] += (int) $r['check_outs'];
        }
        $data['from_date']  = $from_date;
        $data['to_date']    = $to_date;
        $data['page_title'] = 'Check-ins & Check-outs in the Day';
        $data['pagefor']    = 'reports';

        $this->load->view('general/header', $data);
        $this->load->view('Orderportal/Reports/checkin_checkout_count_report', $data);
        $this->load->view('general/footer', $data);
    }

    /** Report 4: Patients Report with Check-ins & Check-outs. */
    public function patientsCheckinCheckoutReport() {
        list($from_date, $to_date) = $this->resolveReportRange();
        $records = $this->getPatientsCheckinCheckout($from_date, $to_date);
        foreach ($records as &$rec) {
            $rec['_fmt'] = $this->formatCheckinCheckoutRow($rec);
        }
        unset($rec);
        $data['records']    = $records;
        $data['from_date']  = $from_date;
        $data['to_date']    = $to_date;
        $data['page_title'] = 'Patients Report with Check-ins & Check-outs';
        $data['pagefor']    = 'reports';

        $this->load->view('general/header', $data);
        $this->load->view('Orderportal/Reports/patients_checkin_checkout_report', $data);
        $this->load->view('general/footer', $data);
    }

    // ---------------- Excel exports ----------------

    /**
     * Generic .xlsx output helper (PhpSpreadsheet).
     * $headers = array of column titles; $rows = array of row arrays;
     * $summaryRows = optional list of [label, value] rows appended after a blank line.
     * $wrapCols = optional list of [colIndex (1-based), width] whose cells wrap text.
     */
    private function outputXlsx($filename, $title, $from_date, $to_date, $headers, $rows, $summaryRows = [], $wrapCols = []) {
        $meta = $this->getReportMeta();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $colCount = count($headers);
        $lastCol  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, $colCount));

        $r = 1;
        $sheet->setCellValue('A' . $r, $title);
        $sheet->mergeCells('A' . $r . ':' . $lastCol . $r);
        $sheet->getStyle('A' . $r)->getFont()->setBold(true)->setSize(14);
        $r++;

        $sheet->setCellValue('A' . $r, 'Date Range: ' . date('d M Y', strtotime($from_date)) . ' to ' . date('d M Y', strtotime($to_date)));
        $sheet->mergeCells('A' . $r . ':' . $lastCol . $r);
        $r++;

        $sheet->setCellValue('A' . $r, 'Generated On: ' . $meta['generated_at']);
        $sheet->mergeCells('A' . $r . ':' . $lastCol . $r);
        $r++;

        $sheet->setCellValue('A' . $r, 'Generated By: ' . $meta['generated_by']);
        $sheet->mergeCells('A' . $r . ':' . $lastCol . $r);
        $r += 2; // blank spacer row

        // Header row
        $headerRow = $r;
        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $r, $h);
            $col++;
        }
        $headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
              ->getStartColor()->setRGB('5156BE');
        $r++;

        // Data rows
        $firstDataRow = $r;
        foreach ($rows as $dataRow) {
            $col = 1;
            foreach ($dataRow as $val) {
                $sheet->setCellValueExplicit(
                    \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $r,
                    (string) $val,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
                $col++;
            }
            $r++;
        }
        $lastDataRow = $r - 1;

        // Summary rows
        if (!empty($summaryRows)) {
            $r++;
            foreach ($summaryRows as $sRow) {
                $col = 1;
                foreach ($sRow as $val) {
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $r, $val);
                    $col++;
                }
                $sheet->getStyle('A' . $r)->getFont()->setBold(true);
                $r++;
            }
        }

        // Columns that should wrap use a fixed width; the rest auto-size.
        $wrapMap = [];
        foreach ($wrapCols as $wc) {
            $idx = (int) $wc[0];
            $wrapMap[$idx] = isset($wc[1]) ? (float) $wc[1] : 60;
        }

        for ($c = 1; $c <= $colCount; $c++) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            if (isset($wrapMap[$c])) {
                $sheet->getColumnDimension($letter)->setWidth($wrapMap[$c]);
                if (!empty($rows)) {
                    $range = $letter . $firstDataRow . ':' . $letter . $lastDataRow;
                    $sheet->getStyle($range)->getAlignment()->setWrapText(true);
                    $sheet->getStyle($range)->getAlignment()
                          ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
                }
            } else {
                $sheet->getColumnDimension($letter)->setAutoSize(true);
            }
        }

        // Prevent any prior output from corrupting the binary stream.
        while (ob_get_level() > 0) { ob_end_clean(); }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /** Export Report 1: Active Patients. */
    public function exportActivePatients() {
        list($from_date, $to_date) = $this->resolveReportRange();
        $days = $this->getActivePatientsByDay($from_date, $to_date);

        $headers = ['Date', 'Active Patients', 'Patient Names Active', 'Onboarded', 'Discharged'];
        $rows = [];
        $totalActive = 0;
        foreach ($days as $d) {
            $totalActive += (int) $d['active'];
            $rows[] = [
                date('d M Y', strtotime($d['date'])),
                (int) $d['active'],
                implode(', ', $d['names']),
                (int) $d['onboarded'],
                (int) $d['discharged'],
            ];
        }
        $summary = [['Total Active Patient-Days', $totalActive]];

        $this->outputXlsx(
            'active_patients_report_' . date('Y-m-d_His') . '.xlsx',
            'Total Number of Active Patients',
            $from_date, $to_date, $headers, $rows, $summary,
            [[3, 70]] // wrap the "Patient Names Active" column
        );
    }

    /** Export Report 2: Patients Ordered Food. */
    public function exportFoodOrders() {
        list($from_date, $to_date) = $this->resolveReportRange();
        $data = $this->getFoodOrdersByDay($from_date, $to_date);

        $headers = ['Date', 'No. of Patients Ordered'];
        $rows = [];
        $total = 0;
        foreach ($data as $d) {
            $total += (int) $d['patient_count'];
            $rows[] = [
                date('d M Y', strtotime($d['order_date'])),
                (int) $d['patient_count'],
            ];
        }
        $summary = [['Total', $total]];

        $this->outputXlsx(
            'food_orders_report_' . date('Y-m-d_His') . '.xlsx',
            'Total Number of Patients Ordered Food',
            $from_date, $to_date, $headers, $rows, $summary
        );
    }

    /** Export Report 3: Check-ins & Check-outs count. */
    public function exportCheckinCheckoutCount() {
        list($from_date, $to_date) = $this->resolveReportRange();
        $data = $this->getCheckinCheckoutByDay($from_date, $to_date);

        $headers = ['Date', 'Check-ins', 'Check-outs'];
        $rows = [];
        $totalIn = 0; $totalOut = 0;
        foreach ($data as $d) {
            $totalIn  += (int) $d['check_ins'];
            $totalOut += (int) $d['check_outs'];
            $rows[] = [
                date('d M Y', strtotime($d['date'])),
                (int) $d['check_ins'],
                (int) $d['check_outs'],
            ];
        }
        $summary = [
            ['Total Check-ins', $totalIn],
            ['Total Check-outs', $totalOut],
        ];

        $this->outputXlsx(
            'checkin_checkout_count_' . date('Y-m-d_His') . '.xlsx',
            'Check-ins & Check-outs in the Day',
            $from_date, $to_date, $headers, $rows, $summary
        );
    }

    /** Export Report 4: Patients with Check-ins & Check-outs. */
    public function exportPatientsCheckinCheckout() {
        list($from_date, $to_date) = $this->resolveReportRange();
        $records = $this->getPatientsCheckinCheckout($from_date, $to_date);

        $headers = ['Patient Name', 'Suite No', 'Check-in', 'Check-out', 'Status', 'Duration'];
        $rows = [];
        foreach ($records as $rec) {
            $fmt = $this->formatCheckinCheckoutRow($rec);
            $rows[] = [
                $rec['patient_name'] ?: 'N/A',
                $rec['suite_number'] ?: 'N/A',
                $fmt['checkin'],
                $fmt['checkout'],
                $fmt['status'],
                $fmt['duration'],
            ];
        }
        $summary = [['Total Patients', count($records)]];

        $this->outputXlsx(
            'patients_checkin_checkout_' . date('Y-m-d_His') . '.xlsx',
            'Patients Report with Check-ins & Check-outs',
            $from_date, $to_date, $headers, $rows, $summary
        );
    }

    /**
     * List all order snapshots
     * Shows comprehensive view of all historical snapshots
     */
    public function snapshots() {
        try {
            $data['page_title'] = 'Order Snapshots - Historical Records';
            $data['pagefor'] = 'reports';
            
            // Load snapshot model
            $this->load->model('Snapshot_model');
            
            // Get filters
            $fromDate = $this->input->get('from_date') ?: date('Y-m-d', strtotime('-30 days'));
            $toDate = $this->input->get('to_date') ?: date('Y-m-d');
            $floorId = $this->input->get('floor_id') ?: null;
            
            // Get all snapshots with filters
            $snapshots = $this->Snapshot_model->getAllSnapshots($fromDate, $toDate, $floorId);
            $data['snapshots'] = is_array($snapshots) ? $snapshots : [];
            $data['from_date'] = $fromDate;
            $data['to_date'] = $toDate;
            $data['floor_id'] = $floorId;
            
            // Get floors for filter dropdown
            $floors = $this->common_model->fetchRecordsDynamically('foodmenuconfig', '*', [
                'listtype' => 'floor',
                'is_deleted' => '0'
            ]);
            $data['floors'] = is_array($floors) ? $floors : [];
            
            // Calculate statistics - safe array handling
            $data['total_snapshots'] = count($data['snapshots']);
            $data['total_orders'] = 0;
            if (!empty($data['snapshots']) && is_array($data['snapshots'])) {
                $orderIds = array_column($data['snapshots'], 'order_id');
                $data['total_orders'] = count(array_unique($orderIds));
            }
            
            // Load views
            $this->load->view('general/header', $data);
            $this->load->view('Reports/snapshots_list', $data);
            $this->load->view('general/footer', $data);
            
        } catch (Exception $e) {
            log_message('error', 'Snapshots page error: ' . $e->getMessage());
            show_error('Unable to load snapshots page. Please check if all required tables exist. Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * View historical order snapshot (immutable data)
     * 
     * @param int $snapshotId The order_snapshots.id
     */
    public function viewOrderSnapshot($snapshotId) {
        $data['page_title'] = 'Order Snapshot - Historical View';
        $data['pagefor'] = 'reports';
        
        // Load snapshot model
        $this->load->model('Snapshot_model');
        
        // Get complete snapshot
        $data['snapshot'] = $this->Snapshot_model->getOrderSnapshot($snapshotId);
        
        if (empty($data['snapshot'])) {
            $this->session->set_flashdata('error', 'Snapshot not found.');
            redirect('Orderportal/Reports');
            return;
        }
        
        // ✅ Fetch allergen names for converting IDs to names
        $conditionsAllergen = ['listtype' => 'allergen', 'is_deleted' => 0];
        $allergensData = $this->common_model->fetchRecordsDynamically('foodmenuconfig', ['id', 'name'], $conditionsAllergen);
        
        // Create allergen ID to name mapping
        $allergenMap = [];
        if (!empty($allergensData)) {
            foreach ($allergensData as $allergen) {
                $allergenMap[$allergen['id']] = $allergen['name'];
            }
        }
        $data['allergenMap'] = $allergenMap;
        
        // Load views
        $this->load->view('general/header', $data);
        $this->load->view('Reports/order_snapshot_view', $data);
        $this->load->view('general/footer', $data);
    }
    
    /**
     * View snapshot by original order ID
     * 
     * @param int $orderId The orders.order_id
     */
    public function viewOrderSnapshotByOrderId($orderId) {
        // Load snapshot model
        $this->load->model('Snapshot_model');
        
        // Get snapshot by order ID
        $snapshot = $this->Snapshot_model->getOrderSnapshotByOrderId($orderId);
        
        if (empty($snapshot)) {
            $this->session->set_flashdata('error', 'No snapshot found for this order. It may have been created before the snapshot system was implemented.');
            redirect('Orderportal/Reports');
            return;
        }
        
        // Redirect to the snapshot view
        redirect('Orderportal/Reports/viewOrderSnapshot/' . $snapshot['id']);
    }
    
    /**
     * Cancelled Orders Report - View all orders cancelled due to patient discharge
     * Shows audit trail of cancelled order items with patient/suite snapshots
     */
    public function cancelledOrders() {
        // Get date range from request or default to last 30 days
        $from_date = $this->input->get('from_date') ?: date('Y-m-d', strtotime('-30 days'));
        $to_date = $this->input->get('to_date') ?: date('Y-m-d');
        $reason_filter = $this->input->get('reason') ?: '';
        
        // Fetch cancelled order items
        $data['cancelled_items'] = $this->getCancelledOrderItems($from_date, $to_date, $reason_filter);
        
        // Get summary stats
        $data['summary'] = $this->getCancelledOrdersSummary($from_date, $to_date);
        
        // Get unique cancel reasons for filter dropdown
        $data['cancel_reasons'] = $this->getUniqueCancelReasons();
        
        // Pass filter values to view
        $data['from_date'] = $from_date;
        $data['to_date'] = $to_date;
        $data['reason_filter'] = $reason_filter;
        
        // Page title
        $data['page_title'] = 'Cancelled Orders Report';
        
        // Load views
        $this->load->view('general/header', $data);
        $this->load->view('Orderportal/Reports/cancelled_orders', $data);
        $this->load->view('general/footer', $data);
    }
    
    /**
     * Get cancelled order items with full details
     */
    private function getCancelledOrderItems($from_date, $to_date, $reason_filter = '') {
        $sql = "SELECT 
                    opo.id,
                    opo.order_id,
                    opo.bed_id,
                    opo.patient_id,
                    opo.menu_id,
                    opo.category_id,
                    opo.option_id,
                    opo.quantity,
                    opo.is_cancelled,
                    opo.cancel_reason,
                    opo.cancelled_at,
                    opo.cancelled_by,
                    opo.patient_name_snapshot,
                    opo.suite_name_snapshot,
                    o.date as order_date,
                    s.bed_no as suite_number,
                    fmc.name as floor_name,
                    fc.name as category_name,
                    md.name as menu_name,
                    mo.menu_option_name,
                    CONCAT(u.first_name, ' ', u.last_name) as cancelled_by_name,
                    p.name as current_patient_name
                FROM orders_to_patient_options opo
                LEFT JOIN orders o ON o.order_id = opo.order_id
                LEFT JOIN suites s ON s.id = opo.bed_id
                LEFT JOIN foodmenuconfig fmc ON fmc.id = s.floor
                LEFT JOIN foodmenuconfig fc ON fc.id = opo.category_id AND fc.listtype = 'category'
                LEFT JOIN menuDetails md ON md.id = opo.menu_id
                LEFT JOIN menu_options mo ON mo.id = opo.option_id
                LEFT JOIN Global_users u ON u.id = opo.cancelled_by
                LEFT JOIN people p ON p.id = opo.patient_id
                WHERE opo.is_cancelled = 1
                AND (
                    (opo.cancelled_at >= ? AND opo.cancelled_at <= ?)
                    OR (opo.cancelled_at IS NULL AND o.date >= ? AND o.date <= ?)
                )";
        
        $params = [
            $from_date . ' 00:00:00', $to_date . ' 23:59:59',
            $from_date, $to_date
        ];
        
        if (!empty($reason_filter)) {
            $sql .= " AND opo.cancel_reason LIKE ?";
            $params[] = '%' . $reason_filter . '%';
        }
        
        $sql .= " ORDER BY COALESCE(opo.cancelled_at, o.date) DESC, opo.order_id, opo.id";
        
        $query = $this->tenantDb->query($sql, $params);
        return $query->result_array();
    }
    
    /**
     * Get summary statistics for cancelled orders
     */
    private function getCancelledOrdersSummary($from_date, $to_date) {
        $sql = "SELECT 
                    COUNT(*) as total_cancelled_items,
                    COUNT(DISTINCT opo.order_id) as affected_orders,
                    COUNT(DISTINCT opo.bed_id) as affected_suites,
                    COUNT(DISTINCT opo.patient_id) as affected_patients,
                    SUM(opo.quantity) as total_quantity_cancelled
                FROM orders_to_patient_options opo
                LEFT JOIN orders o ON o.order_id = opo.order_id
                WHERE opo.is_cancelled = 1
                AND (
                    (opo.cancelled_at >= ? AND opo.cancelled_at <= ?)
                    OR (opo.cancelled_at IS NULL AND o.date >= ? AND o.date <= ?)
                )";
        
        $query = $this->tenantDb->query($sql, [
            $from_date . ' 00:00:00', $to_date . ' 23:59:59',
            $from_date, $to_date
        ]);
        return $query->row_array();
    }
    
    /**
     * Get unique cancel reasons for filter dropdown
     */
    private function getUniqueCancelReasons() {
        $sql = "SELECT DISTINCT cancel_reason 
                FROM orders_to_patient_options 
                WHERE is_cancelled = 1 
                AND cancel_reason IS NOT NULL 
                AND cancel_reason != ''
                ORDER BY cancel_reason";
        
        $query = $this->tenantDb->query($sql);
        return array_column($query->result_array(), 'cancel_reason');
    }
    
    /**
     * Export cancelled orders to CSV
     */
    public function exportCancelledOrders() {
        $from_date = $this->input->post('from_date') ?: date('Y-m-d', strtotime('-30 days'));
        $to_date = $this->input->post('to_date') ?: date('Y-m-d');
        $reason_filter = $this->input->post('reason') ?: '';
        
        $cancelled_items = $this->getCancelledOrderItems($from_date, $to_date, $reason_filter);
        
        // Prepare CSV data
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="cancelled_orders_report_' . date('Y-m-d_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Title
        fputcsv($output, ['Cancelled Orders Report']);
        fputcsv($output, ['Date Range: ' . date('d M Y', strtotime($from_date)) . ' to ' . date('d M Y', strtotime($to_date))]);
        fputcsv($output, ['Generated: ' . date('d M Y H:i:s')]);
        fputcsv($output, []); // Empty row
        
        // CSV Headers
        fputcsv($output, [
            'Cancelled Date',
            'Order ID',
            'Order Date',
            'Suite/Bed',
            'Floor',
            'Patient Name (at cancellation)',
            'Category',
            'Menu Item',
            'Option',
            'Quantity',
            'Cancel Reason',
            'Cancelled By'
        ]);
        
        // CSV Data
        foreach ($cancelled_items as $item) {
            fputcsv($output, [
                $item['cancelled_at'] ? date('d M Y H:i', strtotime($item['cancelled_at'])) : 'N/A',
                $item['order_id'],
                $item['order_date'] ? date('d M Y', strtotime($item['order_date'])) : 'N/A',
                $item['suite_name_snapshot'] ?: $item['suite_number'] ?: 'N/A',
                $item['floor_name'] ?: 'N/A',
                $item['patient_name_snapshot'] ?: 'N/A',
                $item['category_name'] ?: 'N/A',
                $item['menu_name'] ?: 'N/A',
                $item['menu_option_name'] ?: 'N/A',
                $item['quantity'],
                $item['cancel_reason'] ?: 'N/A',
                $item['cancelled_by_name'] ?: 'System'
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // PATIENT AUDIT TRAIL REPORT - Tracks Onboarding, Discharge, and Room Transfers
    // ═══════════════════════════════════════════════════════════════════════════════
    
    /**
     * Patient Audit Trail Report Page
     * Shows detailed tracking of:
     * - Patient onboarding (date + EXACT TIME recorded)
     * - Patient discharges (date + EXACT TIME recorded)  
     * - Room transfers (date + EXACT TIME + from/to rooms)
     */
    public function patientAuditTrail() {
        $this->load->helper('custom');
        
        $data = [];
        $data['page_title'] = 'Patient Audit Trail';
        $data['pagefor'] = 'reports';
        
        // Get date filters from POST or default to last 30 days
        $from_date = $this->input->post('from_date');
        $to_date = $this->input->post('to_date');
        $event_type = $this->input->post('event_type'); // onboarding, discharge, transfer, or all
        
        if (empty($from_date)) {
            $from_date = date('Y-m-d', strtotime('-30 days'));
        }
        if (empty($to_date)) {
            $to_date = date('Y-m-d');
        }
        if (empty($event_type)) {
            $event_type = 'all';
        }
        
        $data['from_date'] = $from_date;
        $data['to_date'] = $to_date;
        $data['selected_event_type'] = $event_type;
        
        // Try to load from audit_log table first, fallback to people table
        $data['audit_events'] = $this->getPatientAuditEvents($from_date, $to_date, $event_type);
        
        // Get summary statistics
        $data['summary'] = $this->getAuditSummary($from_date, $to_date);
        
        $this->load->view('general/header', $data);
        $this->load->view('Orderportal/Reports/patient_audit_trail', $data);
        $this->load->view('general/footer', $data);
    }
    
    /**
     * Get patient audit events from audit_log table (or fallback to people table)
     */
    private function getPatientAuditEvents($from_date, $to_date, $event_type = 'all') {
        $events = [];
        
        // Check if patient_audit_log table exists
        $auditTableExists = $this->tenantDb->table_exists('patient_audit_log');
        
        if ($auditTableExists) {
            // Use the new audit trail table with fallback joins to people/suites for missing data
            $this->tenantDb->select('
                pal.*,
                u.username as user_username,
                p.suite_number as current_suite_id,
                p.floor_number as current_floor_id,
                s.bed_no as current_suite_name,
                f.name as current_floor_name
            ');
            $this->tenantDb->from('patient_audit_log pal');
            $this->tenantDb->join('Global_users u', 'u.id = pal.created_by', 'left');
            $this->tenantDb->join('people p', 'p.id = pal.patient_id', 'left');
            $this->tenantDb->join('suites s', 's.id = p.suite_number', 'left');
            $this->tenantDb->join('foodmenuconfig f', 'f.id = s.floor AND f.listtype = "floor"', 'left');
            $this->tenantDb->where('pal.event_date >=', $from_date);
            $this->tenantDb->where('pal.event_date <=', $to_date);
            
            if ($event_type != 'all') {
                $this->tenantDb->where('pal.event_type', $event_type);
            }
            
            $this->tenantDb->order_by('pal.event_datetime', 'DESC');
            $results = $this->tenantDb->get()->result_array();
            
            foreach ($results as $row) {
                // Determine suite/floor based on event type, with fallback to people table data
                $suite_name = '';
                $floor_name = '';
                
                if ($row['event_type'] == 'onboarding') {
                    $suite_name = $row['new_suite_number'] ?: $row['current_suite_name'] ?: '';
                    $floor_name = $row['new_floor_name'] ?: $row['current_floor_name'] ?: '';
                } elseif ($row['event_type'] == 'discharge') {
                    $suite_name = $row['old_suite_number'] ?: $row['current_suite_name'] ?: '';
                    $floor_name = $row['old_floor_name'] ?: $row['current_floor_name'] ?: '';
                } else {
                    // Transfer or other - use new values, then old, then current
                    $suite_name = $row['new_suite_number'] ?: $row['old_suite_number'] ?: $row['current_suite_name'] ?: '';
                    $floor_name = $row['new_floor_name'] ?: $row['old_floor_name'] ?: $row['current_floor_name'] ?: '';
                }
                
                // Use stored created_by_name, fallback to joined username, then 'System'
                $created_by = $row['created_by_name'] ?: ($row['user_username'] ?: 'System');
                
                $events[] = [
                    'id' => $row['id'],
                    'patient_id' => $row['patient_id'],
                    'patient_name' => $row['patient_name'],
                    'event_type' => $row['event_type'],
                    'event_date' => date('Y-m-d', strtotime($row['event_datetime'])),
                    'event_time' => date('H:i:s', strtotime($row['event_datetime'])),
                    'event_datetime' => $row['event_datetime'],
                    'suite_name' => $suite_name,
                    'floor_name' => $floor_name,
                    'old_suite_name' => $row['old_suite_number'],
                    'new_suite_name' => $row['new_suite_number'],
                    'meals_cancelled' => $row['meals_cancelled'],
                    'orders_transferred' => $row['orders_affected'] ?? 0,
                    'notes' => $row['notes'],
                    'created_by' => $created_by,
                    'json_data' => isset($row['json_data']) ? $row['json_data'] : null
                ];
            }
        } else {
            // Fallback: Build events from people table with time columns
            $events = $this->buildEventsFromPeopleTable($from_date, $to_date, $event_type);
        }
        
        return $events;
    }
    
    /**
     * Fallback: Build audit events from people table (if audit_log doesn't exist)
     */
    private function buildEventsFromPeopleTable($from_date, $to_date, $event_type) {
        $events = [];
        
        // The suites table uses 'floor' (not 'floor_id') to reference foodmenuconfig.
        // The people table has time_onboarded (datetime) and time_discharged (datetime) columns.
        
        // Get onboarding events
        if ($event_type == 'all' || $event_type == 'onboarding') {
            $sql = "SELECT 
                        p.id as patient_id, 
                        p.name as patient_name, 
                        p.suite_number, 
                        p.floor_number, 
                        p.date_onboarded, 
                        p.time_onboarded,
                        p.date_added, 
                        s.bed_no as suite_name, 
                        f.name as floor_name
                    FROM people p
                    LEFT JOIN suites s ON s.id = p.suite_number
                    LEFT JOIN foodmenuconfig f ON f.id = s.floor AND f.listtype = 'floor'
                    WHERE p.date_onboarded >= ?
                    AND p.date_onboarded <= ?
                    ORDER BY p.date_onboarded DESC";
            
            $query = $this->tenantDb->query($sql, [$from_date, $to_date]);
            
            if ($query && is_object($query)) {
                $onboarding_results = $query->result_array();
                
                foreach ($onboarding_results as $row) {
                    // Use time_onboarded (exact datetime), fallback to date_added, then date_onboarded
                    $event_datetime = !empty($row['time_onboarded']) 
                        ? $row['time_onboarded']
                        : (!empty($row['date_added']) ? $row['date_added'] . ' 00:00:00' : $row['date_onboarded'] . ' 00:00:00');
                    
                    $events[] = [
                        'id' => 'onboard_' . $row['patient_id'],
                        'patient_id' => $row['patient_id'],
                        'patient_name' => $row['patient_name'],
                        'event_type' => 'onboarding',
                        'event_date' => $row['date_onboarded'],
                        'event_time' => date('H:i:s', strtotime($event_datetime)),
                        'event_datetime' => $event_datetime,
                        'suite_name' => $row['suite_name'] ?: ($row['suite_number'] ? 'Suite ID: ' . $row['suite_number'] : 'N/A'),
                        'floor_name' => $row['floor_name'] ?: ($row['floor_number'] ? 'Floor ID: ' . $row['floor_number'] : 'N/A'),
                        'old_suite_name' => null,
                        'new_suite_name' => null,
                        'meals_cancelled' => 0,
                        'orders_transferred' => 0,
                        'notes' => 'Patient onboarded',
                        'created_by' => 'System (legacy data)'
                    ];
                }
            }
        }
        
        // Get discharge events
        if ($event_type == 'all' || $event_type == 'discharge') {
            $sql = "SELECT 
                        p.id as patient_id, 
                        p.name as patient_name, 
                        p.suite_number, 
                        p.floor_number, 
                        p.date_of_discharge, 
                        p.time_discharged,
                        p.date_modified, 
                        s.bed_no as suite_name, 
                        f.name as floor_name
                    FROM people p
                    LEFT JOIN suites s ON s.id = p.suite_number
                    LEFT JOIN foodmenuconfig f ON f.id = s.floor AND f.listtype = 'floor'
                    WHERE p.date_of_discharge >= ?
                    AND p.date_of_discharge <= ?
                    AND p.status = 2
                    ORDER BY p.date_of_discharge DESC";
            
            $query = $this->tenantDb->query($sql, [$from_date, $to_date]);
            
            if ($query && is_object($query)) {
                $discharge_results = $query->result_array();
                
                foreach ($discharge_results as $row) {
                    // Use time_discharged (exact datetime), fallback to date_modified, then date_of_discharge
                    $event_datetime = !empty($row['time_discharged']) 
                        ? $row['time_discharged']
                        : (!empty($row['date_modified']) ? $row['date_modified'] . ' 00:00:00' : $row['date_of_discharge'] . ' 00:00:00');
                    
                    $events[] = [
                        'id' => 'discharge_' . $row['patient_id'],
                        'patient_id' => $row['patient_id'],
                        'patient_name' => $row['patient_name'],
                        'event_type' => 'discharge',
                        'event_date' => $row['date_of_discharge'],
                        'event_time' => date('H:i:s', strtotime($event_datetime)),
                        'event_datetime' => $event_datetime,
                        'suite_name' => $row['suite_name'] ?: ($row['suite_number'] ? 'Suite ID: ' . $row['suite_number'] : 'N/A'),
                        'floor_name' => $row['floor_name'] ?: ($row['floor_number'] ? 'Floor ID: ' . $row['floor_number'] : 'N/A'),
                        'old_suite_name' => null,
                        'new_suite_name' => null,
                        'meals_cancelled' => 0, // Can't determine from legacy data
                        'orders_transferred' => 0,
                        'notes' => 'Patient discharged',
                        'created_by' => 'System (legacy data)'
                    ];
                }
            }
        }
        
        // Get transfer events from patient_audit_log if it exists
        // (Transfers cannot be reconstructed from the people table alone since
        //  it only stores the CURRENT suite, not the transfer history.)
        if ($event_type == 'all' || $event_type == 'transfer') {
            if ($this->tenantDb->table_exists('patient_audit_log')) {
                $sql = "SELECT 
                            pal.id,
                            pal.patient_id,
                            pal.patient_name,
                            pal.event_datetime,
                            pal.event_date,
                            pal.old_suite_id,
                            pal.old_suite_number,
                            pal.old_floor_id,
                            pal.old_floor_name,
                            pal.new_suite_id,
                            pal.new_suite_number,
                            pal.new_floor_id,
                            pal.new_floor_name,
                            pal.notes,
                            pal.orders_affected,
                            pal.created_by_name
                        FROM patient_audit_log pal
                        WHERE pal.event_type = 'transfer'
                        AND pal.event_date >= ?
                        AND pal.event_date <= ?
                        ORDER BY pal.event_datetime DESC";
                
                $query = $this->tenantDb->query($sql, [$from_date, $to_date]);
                
                if ($query && is_object($query)) {
                    $transfer_results = $query->result_array();
                    
                    foreach ($transfer_results as $row) {
                        $events[] = [
                            'id' => $row['id'],
                            'patient_id' => $row['patient_id'],
                            'patient_name' => $row['patient_name'],
                            'event_type' => 'transfer',
                            'event_date' => $row['event_date'],
                            'event_time' => date('H:i:s', strtotime($row['event_datetime'])),
                            'event_datetime' => $row['event_datetime'],
                            'suite_name' => $row['new_suite_number'] ?: $row['old_suite_number'] ?: 'N/A',
                            'floor_name' => $row['new_floor_name'] ?: $row['old_floor_name'] ?: 'N/A',
                            'old_suite_name' => $row['old_suite_number'],
                            'new_suite_name' => $row['new_suite_number'],
                            'meals_cancelled' => 0,
                            'orders_transferred' => $row['orders_affected'] ?? 0,
                            'notes' => $row['notes'],
                            'created_by' => $row['created_by_name'] ?: 'System'
                        ];
                    }
                }
            }
        }
        
        // Sort all events by datetime descending
        usort($events, function($a, $b) {
            return strtotime($b['event_datetime']) - strtotime($a['event_datetime']);
        });
        
        return $events;
    }
    
    /**
     * Get audit summary statistics
     */
    private function getAuditSummary($from_date, $to_date) {
        $summary = [
            'total_onboarding' => 0,
            'total_discharges' => 0,
            'total_transfers' => 0,
            'total_meals_cancelled' => 0,
            'by_day' => []
        ];
        
        // Check if audit table exists
        if ($this->tenantDb->table_exists('patient_audit_log')) {
            // Counts from audit log
            $this->tenantDb->select('event_type, COUNT(*) as count, SUM(meals_cancelled) as meals_cancelled');
            $this->tenantDb->from('patient_audit_log');
            $this->tenantDb->where('event_date >=', $from_date);
            $this->tenantDb->where('event_date <=', $to_date);
            $this->tenantDb->group_by('event_type');
            
            $results = $this->tenantDb->get()->result_array();
            
            foreach ($results as $row) {
                if ($row['event_type'] == 'onboarding') {
                    $summary['total_onboarding'] = (int) $row['count'];
                } elseif ($row['event_type'] == 'discharge') {
                    $summary['total_discharges'] = (int) $row['count'];
                    $summary['total_meals_cancelled'] = (int) $row['meals_cancelled'];
                } elseif ($row['event_type'] == 'transfer') {
                    $summary['total_transfers'] = (int) $row['count'];
                }
            }
            
            // By day breakdown
            $this->tenantDb->select('event_date, event_type, COUNT(*) as count');
            $this->tenantDb->from('patient_audit_log');
            $this->tenantDb->where('event_date >=', $from_date);
            $this->tenantDb->where('event_date <=', $to_date);
            $this->tenantDb->group_by('event_date, event_type');
            $this->tenantDb->order_by('event_date', 'ASC');
            
            $by_day_results = $this->tenantDb->get()->result_array();
            
            foreach ($by_day_results as $row) {
                $date = $row['event_date'];
                if (!isset($summary['by_day'][$date])) {
                    $summary['by_day'][$date] = ['onboarding' => 0, 'discharge' => 0, 'transfer' => 0];
                }
                $summary['by_day'][$date][$row['event_type']] = (int) $row['count'];
            }
        } else {
            // Fallback counts from people table
            // Onboarding count
            $this->tenantDb->where('date_onboarded >=', $from_date);
            $this->tenantDb->where('date_onboarded <=', $to_date);
            $summary['total_onboarding'] = $this->tenantDb->count_all_results('people');
            
            // Discharge count
            $this->tenantDb->where('date_of_discharge >=', $from_date);
            $this->tenantDb->where('date_of_discharge <=', $to_date);
            $this->tenantDb->where('status', 2);
            $summary['total_discharges'] = $this->tenantDb->count_all_results('people');
        }
        
        return $summary;
    }
    
    /**
     * Export patient audit trail to CSV
     */
    public function exportPatientAuditTrail() {
        $this->load->helper('custom');
        
        $from_date = $this->input->post('from_date') ?: date('Y-m-d', strtotime('-30 days'));
        $to_date = $this->input->post('to_date') ?: date('Y-m-d');
        $event_type = $this->input->post('event_type') ?: 'all';
        
        $events = $this->getPatientAuditEvents($from_date, $to_date, $event_type);
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="patient_audit_trail_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Title
        fputcsv($output, ['Patient Audit Trail Report']);
        fputcsv($output, ['Date Range: ' . date('d M Y', strtotime($from_date)) . ' to ' . date('d M Y', strtotime($to_date))]);
        fputcsv($output, ['Event Type Filter: ' . ucfirst($event_type)]);
        fputcsv($output, ['Generated: ' . date('d M Y H:i:s')]);
        fputcsv($output, []);
        
        // Headers
        fputcsv($output, [
            'Event Date',
            'Event Time',
            'Event Type',
            'Patient Name',
            'Suite/Room',
            'Floor',
            'Old Room (Transfers)',
            'New Room (Transfers)',
            'Meals Cancelled',
            'Orders Transferred',
            'Notes',
            'Recorded By'
        ]);
        
        // Data
        foreach ($events as $event) {
            fputcsv($output, [
                date('d M Y', strtotime($event['event_date'])),
                $event['event_time'],
                ucfirst($event['event_type']),
                $event['patient_name'],
                $event['suite_name'],
                $event['floor_name'],
                $event['old_suite_name'] ?: 'N/A',
                $event['new_suite_name'] ?: 'N/A',
                $event['meals_cancelled'],
                $event['orders_transferred'],
                $event['notes'],
                $event['created_by']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Print-friendly version of audit trail
     */
    public function printPatientAuditTrail() {
        $this->load->helper('custom');
        
        $from_date = $this->input->get('from_date') ?: date('Y-m-d', strtotime('-30 days'));
        $to_date = $this->input->get('to_date') ?: date('Y-m-d');
        $event_type = $this->input->get('event_type') ?: 'all';
        
        $data['from_date'] = $from_date;
        $data['to_date'] = $to_date;
        $data['selected_event_type'] = $event_type;
        $data['audit_events'] = $this->getPatientAuditEvents($from_date, $to_date, $event_type);
        $data['summary'] = $this->getAuditSummary($from_date, $to_date);
        $data['page_title'] = 'Patient Audit Trail - Print';
        
        $this->load->view('Orderportal/Reports/patient_audit_trail_print', $data);
    }
}
