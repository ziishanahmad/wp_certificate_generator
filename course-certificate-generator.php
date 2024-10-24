<?php
/*
Plugin Name: Certificate Generator
Description: Generates PDF certificates for course participants based on a CSV file.
Version: 1.0
Author: Azlaan Ahmad
*/

// Include FPDF and FPDI libraries
require_once(plugin_dir_path(__FILE__) . 'FPDF-master/fpdf.php');
require_once(plugin_dir_path(__FILE__) . 'FPDI-master/src/autoload.php');

use setasign\Fpdi\Fpdi;

// Add admin menu
function certificate_generator_menu() {
    add_menu_page('Certificate Generator', 'Certificate Generator', 'manage_options', 'certificate-generator', 'certificate_generator_admin_page');
}
add_action('admin_menu', 'certificate_generator_menu');

// Display the plugin admin page
function certificate_generator_admin_page() {
    ?>
    <div class="wrap">
        <h1>Generate Course Certificates</h1>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="generate_certificate">
            <label for="certificate_type">Choose Certificate Type:</label><br>
            <input type="radio" id="online" name="certificate_type" value="online"> Online Participation<br>
            <input type="radio" id="oncampus" name="certificate_type" value="oncampus"> On-Campus Participation<br><br>

            <label for="csv_file">Upload CSV File:</label><br>
            <input type="file" name="csv_file" accept=".csv"><br><br>

            <input type="submit" name="generate_certificate" value="Generate Certificates">
        </form>
    </div>
    <?php
    if (isset($_GET['certificate_generated']) && $_GET['certificate_generated'] == 'true') {
        echo '<div class="notice notice-success is-dismissible"><p>Certificate generated successfully. <a href="' . esc_url($_GET['certificate_url']) . '">Download Certificate</a></p></div>';
    }
}

// Function to generate certificates using an existing PDF template
function generate_certificates() {
    if (isset($_POST['generate_certificate'])) {
        // Check if file is uploaded
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            // Process the CSV file and generate certificates
            $csvFile = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if ($csvFile === false) {
                wp_die('Failed to open CSV file.');
            }

            // Determine which certificate template to use based on the selected type
            $certificate_type = $_POST['certificate_type'];
            if ($certificate_type == 'oncampus') {
                $templatePath = plugin_dir_path(__FILE__) . 'templates/blank on-campus certificate.pdf';
            } else {
                $templatePath = plugin_dir_path(__FILE__) . 'templates/online_cpc.pdf'; // Path for online certificate
            }

            // Skip the first row (header)
            fgetcsv($csvFile);

            while (($data = fgetcsv($csvFile, 1000, ",")) !== FALSE) {
                // Assuming CSV columns: Name, From, Date of Birth, Place of Birth, Start Date, End Date, Book name, Teacher's Name
                $name = $data[0];
                $from = $data[1];
                $dob = $data[3];
                $place_of_birth = $data[2];
                $start_date = $data[4];
                $end_date = $data[5];
                $book_name = $data[6];
                $teacher_name = $data[8];

                // Load the selected PDF template
                $pdf = new Fpdi();
                
                // Get page count and load the template
                $pageCount = $pdf->setSourceFile($templatePath);
                $templateId = $pdf->importPage(1); // Import the first page of the template
                
                // Get the template size and use it to create a page of the same size
                $size = $pdf->getTemplateSize($templateId);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                
                // Use the template
                $pdf->useTemplate($templateId);

                // Set font for writing text
                $pdf->SetFont('Arial', '', 18);

                // Adjust positions based on your template layout

                // X for sideways Y for up and down

                // Set coordinates for "Participant Name"
                $pdf->SetXY(155, 213);  // Coordinates for "Participant Name"
                $pdf->Write(10, htmlspecialchars($name, ENT_QUOTES, 'UTF-8'));

                // Set coordinates for "aus/from"
                $pdf->SetXY(280, 213);  // Coordinates for "aus/from"
                $pdf->Write(10, htmlspecialchars($from, ENT_QUOTES, 'UTF-8'));

                // Set coordinates for "geboren am/Date of Birth"
                $pdf->SetXY(155, 235);  // Coordinates for "Date of Birth"
                $pdf->Write(10, htmlspecialchars($dob, ENT_QUOTES, 'UTF-8'));

                // Set coordinates for "in/Place of Birth"
                $pdf->SetXY(273, 235);  // Coordinates for "Place of Birth"
                $pdf->Write(10, htmlspecialchars($place_of_birth, ENT_QUOTES, 'UTF-8'));

                // Set coordinates for "vom/Start Date"
                $pdf->SetXY(155, 257);  // Coordinates for "Start Date"
                $pdf->Write(10, htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8'));

                // Set coordinates for "bis zum/End Date"
                $pdf->SetXY(280, 257);  // Coordinates for "End Date"
                $pdf->Write(10, htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8'));

                // Set coordinates for "Book name"
                $pdf->SetXY(125, 344);  // Coordinates for "Book name"
                $pdf->Write(10, htmlspecialchars($book_name, ENT_QUOTES, 'UTF-8'));

                // Set coordinates for "Teacher's Name"
                $pdf->SetXY(180, 370);  // Coordinates for "Teacher's Name"
                $pdf->Write(10, htmlspecialchars($teacher_name, ENT_QUOTES, 'UTF-8'));

                // Add a unique timestamp to the file name to avoid overwriting
                $timestamp = time();
                $upload_dir = wp_upload_dir();
                $pdf_path = $upload_dir['path'] . '/Certificate_' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '_' . $timestamp . '.pdf';

                // Save the generated PDF
                $pdf->Output('F', $pdf_path);

                // Redirect to the form with a success message and download link
                $pdf_url = $upload_dir['url'] . '/Certificate_' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '_' . $timestamp . '.pdf';
                wp_redirect(add_query_arg(array('certificate_generated' => 'true', 'certificate_url' => urlencode($pdf_url)), admin_url('admin.php?page=certificate-generator')));
                exit;
            }
            fclose($csvFile);
        } else {
            wp_die('File upload error: ' . $_FILES['csv_file']['error']);
        }
    } else {
        wp_die('Form not submitted correctly.');
    }
}
add_action('admin_post_generate_certificate', 'generate_certificates');
add_action('admin_post_nopriv_generate_certificate', 'generate_certificates');
