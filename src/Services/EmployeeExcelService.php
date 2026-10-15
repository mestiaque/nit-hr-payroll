<?php

namespace ME\Hr\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ME\Hr\Models\HrClassification;
use ME\Hr\Models\HrDepartment;
use ME\Hr\Models\HrDesignation;
use ME\Hr\Models\HrEmployee;
use ME\Hr\Models\HrEmployeeAddress;
use ME\Hr\Models\HrEmployeeAgeVerification;
use ME\Hr\Models\HrEmployeeBasicInfo;
use ME\Hr\Models\HrEmployeeNominee;
use ME\Hr\Models\HrEmployeeSalaryInfo;
use ME\Hr\Models\HrFloorLine;
use ME\Hr\Models\HrGeoLocation;
use ME\Hr\Models\HrMaritalStatus;
use ME\Hr\Models\HrPaymentMethod;
use ME\Hr\Models\HrReligion;
use ME\Hr\Models\HrSection;
use ME\Hr\Models\HrSex;
use ME\Hr\Models\HrShift;
use ME\Hr\Models\HrSubSection;
use ME\Hr\Models\HrWorkingPlace;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Full employee Excel round trip: export every employee field (profile, basic info,
 * salary info, permanent/present address, nominee, age verification) as one flat row
 * per employee, and import the same layout back.
 *
 * Import upserts by Employee ID — an existing ID updates that employee, a new ID
 * creates one. A blank cell never clears a stored value (it means "leave unchanged"),
 * so an edited export can be re-imported safely. Lookup columns (Department,
 * Designation, District, ...) are matched by name and must already exist; they are
 * never auto-created, so a typo is reported instead of silently adding a new
 * department.
 *
 * Follows CompanyAssetImportService's preview -> recheck -> save row shape so it plugs
 * into the shared hr::partials.excel-import-page UI.
 */
class EmployeeExcelService
{
    private const SHEET_NAME = 'Employees';

    private const DAYS = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    /**
     * [key, heading, group, type, options]. Types: text, date, number, int, percent,
     * bool, enum (options = allowed values), lookup (options = lookup name),
     * readonly (exported for reference, ignored on import).
     */
    private const COLUMNS = [
        ['employee_id', 'Employee ID', 'Job Info', 'text'],
        ['name', 'Name', 'Job Info', 'text'],
        ['bn_name', 'Name (Bangla)', 'Job Info', 'text'],
        ['join_date', 'Joining Date', 'Job Info', 'date'],
        ['classification', 'Classification', 'Job Info', 'lookup', 'classification'],
        ['department', 'Department', 'Job Info', 'lookup', 'department'],
        ['section', 'Section', 'Job Info', 'lookup', 'section'],
        ['sub_section', 'Sub Section', 'Job Info', 'lookup', 'sub_section'],
        ['designation', 'Designation', 'Job Info', 'lookup', 'designation'],
        ['grade', 'Grade', 'Job Info', 'readonly'],
        ['working_place', 'Working Place', 'Job Info', 'lookup', 'working_place'],
        ['shift', 'Shift', 'Job Info', 'lookup', 'shift'],
        ['line', 'Line / Block', 'Job Info', 'lookup', 'line'],
        ['weekend', 'Weekend', 'Job Info', 'enum', self::DAYS],
        ['personal_contact', 'Mobile', 'Job Info', 'text'],
        ['emergency_contact', 'Emergency Mobile', 'Job Info', 'text'],
        ['salary_type', 'Salary Type', 'Job Info', 'enum', ['fixed_rate', 'price_rate']],
        ['comp_one', 'Comp 1 Active', 'Job Info', 'bool'],
        ['comp_two', 'Comp 2 Active', 'Job Info', 'bool'],
        ['status', 'Status', 'Job Info', 'enum', ['Active', 'Inactive']],
        ['employment_status', 'Employment Status', 'Job Info', 'readonly'],
        ['exited_at', 'Exit Date', 'Job Info', 'readonly'],

        ['father_name', 'Father Name', 'Basic Info', 'text'],
        ['bn_father_name', 'Father Name (Bangla)', 'Basic Info', 'text'],
        ['mother_name', 'Mother Name', 'Basic Info', 'text'],
        ['bn_mother_name', 'Mother Name (Bangla)', 'Basic Info', 'text'],
        ['marital_status', 'Marital Status', 'Basic Info', 'lookup', 'marital_status'],
        ['spouse_name', 'Spouse Name', 'Basic Info', 'text'],
        ['bn_spouse_name', 'Spouse Name (Bangla)', 'Basic Info', 'text'],
        ['gender', 'Gender', 'Basic Info', 'lookup', 'gender'],
        ['children_boys', 'Children (Boys)', 'Basic Info', 'int'],
        ['children_girls', 'Children (Girls)', 'Basic Info', 'int'],
        ['religion', 'Religion', 'Basic Info', 'lookup', 'religion'],
        ['birth_date', 'Date of Birth', 'Basic Info', 'date'],
        ['blood_group', 'Blood Group', 'Basic Info', 'text'],
        ['nationality', 'Nationality', 'Basic Info', 'lookup', 'country'],
        ['national_id_no', 'NID No', 'Basic Info', 'text'],
        ['birth_registration_no', 'Birth Registration No', 'Basic Info', 'text'],
        ['passport_no', 'Passport No', 'Basic Info', 'text'],
        ['driving_license_no', 'Driving License No', 'Basic Info', 'text'],
        ['special_id_sign', 'Distinguished Mark', 'Basic Info', 'text'],
        ['bn_special_id_sign', 'Distinguished Mark (Bangla)', 'Basic Info', 'text'],
        ['educational_experience', 'Education', 'Basic Info', 'text'],
        ['bn_educational_experience', 'Education (Bangla)', 'Basic Info', 'text'],
        ['job_experience', 'Job Experience', 'Basic Info', 'text'],
        ['bn_job_experience', 'Job Experience (Bangla)', 'Basic Info', 'text'],
        ['previous_organization', 'Previous Organization', 'Basic Info', 'text'],
        ['bn_previous_organization', 'Previous Organization (Bangla)', 'Basic Info', 'text'],
        ['reference_name', 'Reference 1', 'Basic Info', 'text'],
        ['bn_reference_name', 'Reference 1 (Bangla)', 'Basic Info', 'text'],
        ['reference_designation', 'Reference 2', 'Basic Info', 'text'],
        ['bn_reference_designation', 'Reference 2 (Bangla)', 'Basic Info', 'text'],
        ['reference_card_no', 'Reference Card No', 'Basic Info', 'text'],
        ['bn_reference_card_no', 'Reference Card No (Bangla)', 'Basic Info', 'text'],
        ['reference_mobile_no', 'Reference Mobile', 'Basic Info', 'text'],
        ['bn_reference_mobile_no', 'Reference Mobile (Bangla)', 'Basic Info', 'text'],

        ['gross_salary', 'Gross Salary', 'Salary Info', 'number'],
        ['gross_salary_comp1', 'Gross Salary (Comp 1)', 'Salary Info', 'number'],
        ['gross_salary_comp2', 'Gross Salary (Comp 2)', 'Salary Info', 'number'],
        ['payment_method', 'Payment Method', 'Salary Info', 'lookup', 'payment_method'],
        ['bank_ac_or_phone', 'Bank A/C or Phone', 'Salary Info', 'text'],
        ['car_fuel', 'Car Fuel', 'Salary Info', 'number'],
        ['phone_internet', 'Phone & Internet', 'Salary Info', 'number'],
        ['extra_facility', 'Extra Facility', 'Salary Info', 'number'],
        ['attendance_bonus', 'Attendance Bonus', 'Salary Info', 'number'],
        ['attendance_bonus_com', 'Attendance Bonus (Comp)', 'Salary Info', 'number'],
        ['tiffin_allowance', 'Tiffin Allowance', 'Salary Info', 'number'],
        ['min_tiffin_hour', 'Min Tiffin Hour', 'Salary Info', 'number'],
        ['night_allowance', 'Night Allowance', 'Salary Info', 'number'],
        ['min_night_hour', 'Min Night Hour', 'Salary Info', 'number'],
        ['dinner_allowance', 'Dinner Allowance', 'Salary Info', 'number'],
        ['min_dinner_hour', 'Min Dinner Hour', 'Salary Info', 'number'],
        ['payment_way', 'Meal Payment Way', 'Salary Info', 'enum', ['monthly', 'daily']],
        ['weekend_allowance_count', 'Weekend Allowance Count', 'Salary Info', 'text'],
        ['holiday_allowance', 'Holiday Allowance', 'Salary Info', 'number'],
        ['tax', 'Tax', 'Salary Info', 'number'],
        ['tax_calculate_by', 'Tax Calculate By', 'Salary Info', 'enum', ['percent', 'amount']],
        ['salary_effective_date', 'Salary Effective Date', 'Salary Info', 'date'],

        ['permanent_district', 'Permanent District', 'Permanent Address', 'lookup', 'district'],
        ['permanent_police_station', 'Permanent Police Station', 'Permanent Address', 'lookup', 'police_station'],
        ['permanent_post_office', 'Permanent Post Office', 'Permanent Address', 'text'],
        ['permanent_post_office_bn', 'Permanent Post Office (Bangla)', 'Permanent Address', 'text'],
        ['permanent_village', 'Permanent Village', 'Permanent Address', 'text'],
        ['permanent_village_bn', 'Permanent Village (Bangla)', 'Permanent Address', 'text'],

        ['present_district', 'Present District', 'Present Address', 'lookup', 'district'],
        ['present_police_station', 'Present Police Station', 'Present Address', 'lookup', 'police_station'],
        ['present_post_office', 'Present Post Office', 'Present Address', 'text'],
        ['present_post_office_bn', 'Present Post Office (Bangla)', 'Present Address', 'text'],
        ['present_village', 'Present Village', 'Present Address', 'text'],
        ['present_village_bn', 'Present Village (Bangla)', 'Present Address', 'text'],

        ['nominee_name', 'Nominee Name', 'Nominee', 'text'],
        ['nominee_bn_name', 'Nominee Name (Bangla)', 'Nominee', 'text'],
        ['nominee_relation', 'Nominee Relation', 'Nominee', 'text'],
        ['nominee_bn_relation', 'Nominee Relation (Bangla)', 'Nominee', 'text'],
        ['nominee_age', 'Nominee Age', 'Nominee', 'int'],
        ['nominee_nid_no', 'Nominee NID', 'Nominee', 'text'],
        ['nominee_mobile_no', 'Nominee Mobile', 'Nominee', 'text'],
        ['nominee_district', 'Nominee District', 'Nominee', 'lookup', 'district'],
        ['nominee_police_station', 'Nominee Police Station', 'Nominee', 'lookup', 'police_station'],
        ['nominee_post_office', 'Nominee Post Office', 'Nominee', 'text'],
        ['nominee_post_office_bn', 'Nominee Post Office (Bangla)', 'Nominee', 'text'],
        ['nominee_village', 'Nominee Village', 'Nominee', 'text'],
        ['nominee_village_bn', 'Nominee Village (Bangla)', 'Nominee', 'text'],
        ['nominee_net_payment', 'Nominee % Due Wages', 'Nominee', 'percent'],
        ['nominee_provident_fund', 'Nominee % Provident Fund', 'Nominee', 'percent'],
        ['nominee_insurance', 'Nominee % Insurance', 'Nominee', 'percent'],
        ['nominee_accident_fine', 'Nominee % Accident Compensation', 'Nominee', 'percent'],
        ['nominee_profit', 'Nominee % Profit', 'Nominee', 'percent'],
        ['nominee_others', 'Nominee % Others', 'Nominee', 'percent'],

        ['av_physical_ability', 'Physical Ability', 'Age Verification', 'text'],
        ['av_physical_ability_bn', 'Physical Ability (Bangla)', 'Age Verification', 'text'],
        ['av_identification_mark', 'Identification Mark', 'Age Verification', 'text'],
        ['av_identification_mark_bn', 'Identification Mark (Bangla)', 'Age Verification', 'text'],
        ['av_age_years', 'Verified Age', 'Age Verification', 'int'],
        ['av_verified_date', 'Age Verification Date', 'Age Verification', 'date'],
    ];

    /** employee field key => hr_employees column (keys not listed here map to the same name) */
    private const EMPLOYEE_COLUMNS = [
        'employee_id', 'name', 'bn_name', 'join_date', 'weekend', 'personal_contact',
        'emergency_contact', 'salary_type',
    ];

    private const BASIC_INFO_COLUMNS = [
        'father_name', 'bn_father_name', 'mother_name', 'bn_mother_name', 'spouse_name',
        'bn_spouse_name', 'children_boys', 'children_girls', 'birth_date', 'blood_group',
        'national_id_no', 'birth_registration_no', 'passport_no', 'driving_license_no',
        'special_id_sign', 'bn_special_id_sign', 'educational_experience', 'bn_educational_experience',
        'job_experience', 'bn_job_experience', 'previous_organization', 'bn_previous_organization',
        'reference_name', 'bn_reference_name', 'reference_designation', 'bn_reference_designation',
        'reference_card_no', 'bn_reference_card_no', 'reference_mobile_no', 'bn_reference_mobile_no',
    ];

    private const SALARY_INFO_COLUMNS = [
        'gross_salary', 'gross_salary_comp1', 'gross_salary_comp2', 'bank_ac_or_phone', 'car_fuel',
        'phone_internet', 'extra_facility', 'attendance_bonus', 'attendance_bonus_com',
        'tiffin_allowance', 'min_tiffin_hour', 'night_allowance', 'min_night_hour',
        'dinner_allowance', 'min_dinner_hour', 'payment_way', 'weekend_allowance_count',
        'holiday_allowance', 'tax', 'tax_calculate_by',
    ];

    private const NOMINEE_COLUMNS = [
        'name', 'bn_name', 'relation', 'bn_relation', 'age', 'nid_no', 'mobile_no', 'post_office',
        'bn_post_office', 'village', 'bn_village', 'net_payment', 'provident_fund', 'insurance',
        'accident_fine', 'profit', 'others',
    ];

    /** salary-info column => designation column it defaults from for a new employee */
    private const DESIGNATION_SALARY_DEFAULTS = [
        'gross_salary' => 'gross_salary',
        'gross_salary_comp1' => 'gross_salary',
        'gross_salary_comp2' => 'gross_salary',
        'car_fuel' => 'car_fuel_allowance',
        'phone_internet' => 'phone_internet_allowance',
        'extra_facility' => 'extra_facility',
        'attendance_bonus' => 'attendance_bonus',
        'attendance_bonus_com' => 'attendance_bonus_com',
        'tiffin_allowance' => 'tiffin_allowance',
        'min_tiffin_hour' => 'min_tiffin_hour',
        'night_allowance' => 'night_allowance',
        'min_night_hour' => 'min_night_hour',
        'dinner_allowance' => 'dinner_allowance',
        'min_dinner_hour' => 'min_dinner_hour',
        'payment_way' => 'payment_way',
        'weekend_allowance_count' => 'weekend_allowance_count',
        'holiday_allowance' => 'holiday_allowance',
    ];

    /** varchar sizes of the text columns (anything not listed is a TEXT column) */
    private const MAX_LENGTHS = [
        'employee_id' => 30, 'name' => 150, 'bn_name' => 150, 'personal_contact' => 20, 'emergency_contact' => 20,
        'father_name' => 150, 'bn_father_name' => 150, 'mother_name' => 150, 'bn_mother_name' => 150,
        'spouse_name' => 150, 'bn_spouse_name' => 150, 'blood_group' => 191, 'national_id_no' => 30,
        'birth_registration_no' => 30, 'passport_no' => 30, 'driving_license_no' => 30,
        'previous_organization' => 200, 'bn_previous_organization' => 200, 'reference_name' => 150,
        'bn_reference_name' => 150, 'reference_designation' => 150, 'bn_reference_designation' => 150,
        'reference_card_no' => 50, 'bn_reference_card_no' => 50, 'reference_mobile_no' => 20,
        'bn_reference_mobile_no' => 20, 'bank_ac_or_phone' => 50, 'weekend_allowance_count' => 50,
        'permanent_post_office' => 100, 'permanent_post_office_bn' => 100, 'permanent_village' => 150,
        'permanent_village_bn' => 150, 'present_post_office' => 100, 'present_post_office_bn' => 100,
        'present_village' => 150, 'present_village_bn' => 150, 'nominee_name' => 150, 'nominee_bn_name' => 150,
        'nominee_relation' => 100, 'nominee_bn_relation' => 100, 'nominee_nid_no' => 30, 'nominee_mobile_no' => 20,
        'nominee_post_office' => 100, 'nominee_post_office_bn' => 100, 'nominee_village' => 150,
        'nominee_village_bn' => 150,
    ];

    /** @var array<string, array<int, array{id:int, label:string, names:array, parent:?int}>> */
    private array $lookups = [];

    private ?bool $numericStatus = null;

    // ── Export / template ────────────────────────────────────────────────

    public function export(Builder $query): Spreadsheet
    {
        $employees = $query
            ->with(['basicInfo', 'salaryInfo', 'addresses', 'nomineeRecord', 'ageVerification'])
            ->lazy(500);

        return $this->buildWorkbook($employees);
    }

    public function template(): Spreadsheet
    {
        return $this->buildWorkbook([]);
    }

    private function buildWorkbook(iterable $employees): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);

        $columnCount = count(self::COLUMNS);
        $letters = [];
        foreach (self::COLUMNS as $i => $column) {
            $letters[$i] = Coordinate::stringFromColumnIndex($i + 1);
        }

        // Row 1: section group bands, row 2: the column headings the importer reads.
        $groupColors = [
            'Job Info' => 'C6E0B4', 'Basic Info' => 'BDD7EE', 'Salary Info' => 'FFE699',
            'Permanent Address' => 'F8CBAD', 'Present Address' => 'F4B183', 'Nominee' => 'D9D2E9',
            'Age Verification' => 'C9C9C9',
        ];
        $groupStart = 0;
        foreach (self::COLUMNS as $i => $column) {
            $sheet->setCellValueExplicit($letters[$i].'2', $column[1], DataType::TYPE_STRING);

            $isLastOfGroup = $i === $columnCount - 1 || self::COLUMNS[$i + 1][2] !== $column[2];
            if ($isLastOfGroup) {
                $range = $letters[$groupStart].'1:'.$letters[$i].'1';
                $sheet->setCellValueExplicit($letters[$groupStart].'1', $column[2], DataType::TYPE_STRING);
                if ($groupStart !== $i) {
                    $sheet->mergeCells($range);
                }
                $sheet->getStyle($letters[$groupStart].'1:'.$letters[$i].'2')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($groupColors[$column[2]] ?? 'EEEEEE');
                $groupStart = $i + 1;
            }
        }
        $lastLetter = $letters[$columnCount - 1];
        $sheet->getStyle('A1:'.$lastLetter.'2')->getFont()->setBold(true);
        $sheet->getStyle('A1:'.$lastLetter.'1')->getAlignment()->setHorizontal('center');

        $rowNumber = 2;
        foreach ($employees as $employee) {
            $rowNumber++;
            $values = $this->exportRow($employee);
            foreach (self::COLUMNS as $i => $column) {
                $value = $values[$column[0]] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $isNumeric = in_array($column[3], ['number', 'int', 'percent'], true) && is_numeric($value);
                $sheet->setCellValueExplicit(
                    $letters[$i].$rowNumber,
                    $isNumeric ? $value + 0 : (string) $value,
                    $isNumeric ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING
                );
            }
        }

        // Text-format every non-numeric column (well past the data) so IDs, NID and
        // phone numbers typed in later keep their leading zeros / full digits.
        $formatTo = max($rowNumber, 2) + 1000;
        foreach (self::COLUMNS as $i => $column) {
            if (!in_array($column[3], ['number', 'int', 'percent'], true)) {
                $sheet->getStyle($letters[$i].'3:'.$letters[$i].$formatTo)
                    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
            if ($column[3] === 'readonly') {
                $sheet->getStyle($letters[$i].'2')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('A6A6A6');
            }
            $sheet->getColumnDimension($letters[$i])->setWidth(max(12, min(32, mb_strlen($column[1]) + 4)));
        }
        $sheet->freezePane('C3');

        $this->addInstructionsSheet($spreadsheet);
        $this->addValidValuesSheet($spreadsheet);
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function exportRow(HrEmployee $employee): array
    {
        $basic = $employee->basicInfo;
        $salary = $employee->salaryInfo;
        $nominee = $employee->nomineeRecord;
        $av = $employee->ageVerification;
        $addresses = $employee->addresses->keyBy('type');

        $row = [];
        foreach (self::EMPLOYEE_COLUMNS as $column) {
            $row[$column] = $employee->getRawOriginal($column);
        }
        $row['join_date'] = $this->formatDate($employee->getRawOriginal('join_date'));
        $row['classification'] = $this->lookupLabel('classification', $employee->classification_id);
        $row['department'] = $this->lookupLabel('department', $employee->department_id);
        $row['section'] = $this->lookupLabel('section', $employee->section_id);
        $row['sub_section'] = $this->lookupLabel('sub_section', $employee->sub_section_id);
        $row['designation'] = $this->lookupLabel('designation', $employee->designation_id);
        $row['grade'] = $employee->grade;
        $row['working_place'] = $this->lookupLabel('working_place', $employee->working_place_id);
        $row['shift'] = $this->lookupLabel('shift', $employee->shift_id);
        $row['line'] = $this->lookupLabel('line', $employee->floor_line_id);
        $row['comp_one'] = $employee->comp_one ? 'Yes' : 'No';
        $row['comp_two'] = $employee->comp_two ? 'Yes' : 'No';
        $status = $employee->getRawOriginal('status');
        $row['status'] = is_numeric($status) ? ((int) $status === 1 ? 'Active' : 'Inactive') : ucfirst((string) $status);
        $row['employment_status'] = $employee->employment_status ?: 'regular';
        $row['exited_at'] = $this->formatDate($employee->getRawOriginal('exited_at'));

        if ($basic) {
            foreach (self::BASIC_INFO_COLUMNS as $column) {
                $row[$column] = $basic->getRawOriginal($column);
            }
            $row['birth_date'] = $this->formatDate($basic->getRawOriginal('birth_date'));
            $row['marital_status'] = $this->lookupLabel('marital_status', $basic->marital_status_id);
            $row['gender'] = $this->lookupLabel('gender', $basic->sex_id);
            $row['religion'] = $this->lookupLabel('religion', $basic->religion_id);
            $row['nationality'] = $this->lookupLabel('country', $basic->nationality_country_id);
        }

        if ($salary) {
            foreach (self::SALARY_INFO_COLUMNS as $column) {
                $row[$column] = $salary->getRawOriginal($column);
            }
            $row['salary_effective_date'] = $this->formatDate($salary->getRawOriginal('effective_date'));
        }
        $row['payment_method'] = $this->lookupLabel('payment_method', $salary?->payment_method_id ?? $basic?->payment_method_id);

        foreach (['permanent', 'present'] as $type) {
            $address = $addresses->get($type);
            if (!$address) {
                continue;
            }
            $row[$type.'_district'] = $this->lookupLabel('district', $address->district_id);
            $row[$type.'_police_station'] = $this->lookupLabel('police_station', $address->police_station_id);
            $row[$type.'_post_office'] = $address->post_office;
            $row[$type.'_post_office_bn'] = $address->bn_post_office;
            $row[$type.'_village'] = $address->village;
            $row[$type.'_village_bn'] = $address->bn_village;
        }

        if ($nominee) {
            foreach (self::NOMINEE_COLUMNS as $column) {
                $row[$this->nomineeKey($column)] = $nominee->getRawOriginal($column);
            }
            $row['nominee_district'] = $this->lookupLabel('district', $nominee->district_id);
            $row['nominee_police_station'] = $this->lookupLabel('police_station', $nominee->police_station_id);
        }

        if ($av) {
            $row['av_physical_ability'] = $av->physical_ability;
            $row['av_physical_ability_bn'] = $av->physical_ability_bn;
            $row['av_identification_mark'] = $av->identification_mark;
            $row['av_identification_mark_bn'] = $av->identification_mark_bn;
            $row['av_age_years'] = $av->age_years;
            $row['av_verified_date'] = $this->formatDate($av->getRawOriginal('verified_date'));
        }

        return $row;
    }

    private function addInstructionsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instructions');
        $lines = [
            ['Employee Import / Export — Instructions'],
            [''],
            ['Fill the "Employees" sheet, one row per employee. Keep the heading row (row 2) as it is; column order does not matter.'],
            ['Employee ID is the key: an Employee ID that already exists UPDATES that employee, a new Employee ID CREATES a new employee.'],
            ['Employee ID and Name are required for a new employee. Everything else is optional.'],
            ['A blank cell means "leave unchanged" — it never erases saved data. To clear a value, edit that employee in the software.'],
            ['Lookup columns (Classification, Department, Section, Sub Section, Designation, Working Place, Shift, Line / Block, Marital Status, Gender, Religion, Nationality, Payment Method, District, Police Station) must match a name that already exists in the software — see the "Valid Values" sheet. Unknown names are reported as errors, nothing is created automatically.'],
            ['Section must belong to the given Department, and Sub Section to the given Section.'],
            ['Dates: use YYYY-MM-DD (e.g. 2024-01-15) or DD/MM/YYYY (e.g. 15/01/2024).'],
            ['Status: Active or Inactive. Comp 1 / Comp 2 Active: Yes or No. Salary Type: fixed_rate or price_rate. Meal Payment Way: monthly or daily. Tax Calculate By: percent or amount.'],
            ['Weekend: a day name (Saturday ... Friday).'],
            ['Nominee % columns are percentages between 0 and 100.'],
            ['Grey columns (Grade, Employment Status, Exit Date) are for reference only and are ignored on import. Grade follows the Designation; Employment Status / Exit Date are changed through the Lefty / Resign screen.'],
            ['For a new employee, salary fields left blank are filled from the Designation\'s default salary settings.'],
        ];
        $sheet->fromArray($lines, null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getColumnDimension('A')->setWidth(140);
        $sheet->getStyle('A3:A'.count($lines))->getAlignment()->setWrapText(true);
    }

    private function addValidValuesSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Valid Values');

        $lists = [
            'Classification' => 'classification', 'Department' => 'department', 'Section' => 'section',
            'Sub Section' => 'sub_section', 'Designation' => 'designation', 'Working Place' => 'working_place',
            'Shift' => 'shift', 'Line / Block' => 'line', 'Marital Status' => 'marital_status',
            'Gender' => 'gender', 'Religion' => 'religion', 'Nationality' => 'country',
            'Payment Method' => 'payment_method', 'District' => 'district', 'Police Station' => 'police_station',
        ];

        $col = 1;
        foreach ($lists as $heading => $lookup) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValueExplicit($letter.'1', $heading, DataType::TYPE_STRING);
            $labels = collect($this->lookup($lookup))->pluck('label')->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
            foreach ($labels as $i => $label) {
                $sheet->setCellValueExplicit($letter.($i + 2), $label, DataType::TYPE_STRING);
            }
            $sheet->getColumnDimension($letter)->setWidth(24);
            $col++;
        }
        $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex($col - 1).'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
    }

    // ── Import ───────────────────────────────────────────────────────────

    public function preview(UploadedFile $file): array
    {
        return $this->process($file, save: false);
    }

    public function import(UploadedFile $file): array
    {
        return $this->process($file, save: true);
    }

    /**
     * Saves rows already parsed by preview() (and possibly edited inline in the
     * import page), so inline corrections are what gets saved rather than the
     * original file contents.
     */
    public function importRows(array $rows): array
    {
        $fieldKeys = $this->importableKeys();
        $seen = [];
        $results = [];

        foreach ($rows as $row) {
            $fields = [];
            foreach ($fieldKeys as $key) {
                $fields[$key] = trim((string) ($row[$key] ?? ''));
            }
            $rowNumber = $row['row'] ?? null;
            $results[] = ['row' => $rowNumber, ...$this->validateRowOnce($fields, true, $seen, $rowNumber)];
        }

        return ['error' => null, 'rows' => $results];
    }

    private function process(UploadedFile $file, bool $save): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable $e) {
            return ['error' => 'Could not read this file. Please make sure it is a valid .xlsx/.xls file.', 'rows' => []];
        }

        $sheet = $this->findSheet($spreadsheet);
        $grid = $sheet->toArray(null, true, false, false);

        $headingToKey = [];
        foreach (self::COLUMNS as $column) {
            if ($column[3] !== 'readonly') {
                $headingToKey[$this->normalizeHeading($column[1])] = $column[0];
            }
        }

        // The heading row is row 2 in our own file (row 1 holds the section bands),
        // but a hand-made sheet may put it anywhere near the top.
        $headerRowIndex = null;
        foreach (array_slice($grid, 0, 10, true) as $i => $candidate) {
            if (in_array('employee id', array_map(fn ($h) => $this->normalizeHeading($h), $candidate), true)) {
                $headerRowIndex = $i;
                break;
            }
        }
        if ($headerRowIndex === null) {
            return ['error' => 'Could not find the "Employee ID" column. Please use the template or an exported file.', 'rows' => []];
        }

        $columnIndex = [];
        foreach ($grid[$headerRowIndex] as $i => $heading) {
            $key = $headingToKey[$this->normalizeHeading($heading)] ?? null;
            if ($key !== null && !isset($columnIndex[$key])) {
                $columnIndex[$key] = $i;
            }
        }

        $typeByKey = array_column(self::COLUMNS, 3, 0);
        $fieldKeys = $this->importableKeys();
        $seen = [];
        $results = [];

        foreach (array_slice($grid, $headerRowIndex + 1) as $offset => $cells) {
            $fields = [];
            foreach ($fieldKeys as $key) {
                $raw = isset($columnIndex[$key]) ? ($cells[$columnIndex[$key]] ?? null) : null;
                $fields[$key] = $this->cellToString($raw, $typeByKey[$key] === 'date');
            }

            if (empty(array_filter($fields, fn ($v) => $v !== ''))) {
                continue;
            }

            $rowNumber = $headerRowIndex + 2 + $offset;
            $results[] = ['row' => $rowNumber, ...$this->validateRowOnce($fields, $save, $seen, $rowNumber)];
        }

        return ['error' => null, 'rows' => $results];
    }

    private function validateRowOnce(array $fields, bool $save, array &$seen, $rowNumber): array
    {
        $id = strtolower($fields['employee_id']);
        if ($id !== '' && isset($seen[$id])) {
            $result = $this->validateRow($fields, false);
            $result['errors'][] = "Employee ID '{$fields['employee_id']}' appears more than once in this file (first at row {$seen[$id]}).";

            return $result;
        }
        if ($id !== '') {
            $seen[$id] = $rowNumber;
        }

        return $this->validateRow($fields, $save);
    }

    /**
     * Validates one row's fields (same shape for file rows and the inline-edit recheck
     * endpoint) and, when $save is true and the row is valid, writes it. Returns the
     * row's display fields plus 'action', 'errors' and 'saved'.
     */
    public function validateRow(array $fields, bool $save = false): array
    {
        $types = [];
        foreach (self::COLUMNS as $column) {
            $types[$column[0]] = $column;
        }

        $input = [];
        foreach ($this->importableKeys() as $key) {
            $input[$key] = trim((string) ($fields[$key] ?? ''));
        }

        $errors = [];
        $employeeId = $input['employee_id'];
        $existing = $employeeId !== '' ? HrEmployee::query()->where('employee_id', $employeeId)->first() : null;

        if ($employeeId === '') {
            $errors[] = 'Employee ID is required.';
        }
        if (!$existing && $input['name'] === '') {
            $errors[] = 'Name is required for a new employee.';
        }

        // Parsed values for every non-blank cell; blank cells are simply absent.
        $values = [];
        foreach ($input as $key => $raw) {
            if ($raw === '' || in_array($types[$key][3], ['lookup'], true)) {
                continue;
            }
            $label = $types[$key][1];
            $parsed = $this->parseValue($types[$key], $raw, $error);
            if ($error !== null) {
                $errors[] = "{$label}: {$error}";
                continue;
            }
            $values[$key] = $parsed;
        }

        // Lookups, resolved parent-first so Section/Sub Section/Police Station can be
        // checked against (or disambiguated by) their Department/Section/District.
        $departmentId = null;
        $sectionId = null;
        $resolve = function (string $key, ?int $parentId = null, bool $strict = false) use ($input, $types, &$errors, &$values) {
            if ($input[$key] === '') {
                return null;
            }
            $id = $this->resolveLookup($types[$key][4], $input[$key], $parentId, $strict, $error);
            if ($error !== null) {
                $errors[] = "{$types[$key][1]}: {$error}";

                return null;
            }

            return $values[$key] = $id;
        };

        $departmentId = $resolve('department') ?? $existing?->department_id;
        $sectionId = $resolve('section', $departmentId ? (int) $departmentId : null, true) ?? $existing?->section_id;
        $resolve('sub_section', $sectionId ? (int) $sectionId : null, true);
        $resolve('designation', $departmentId ? (int) $departmentId : null);
        foreach (['classification', 'working_place', 'shift', 'line', 'marital_status', 'gender', 'religion', 'nationality', 'payment_method'] as $key) {
            $resolve($key);
        }
        foreach (['permanent', 'present', 'nominee'] as $prefix) {
            $districtId = $resolve($prefix.'_district');
            $resolve($prefix.'_police_station', $districtId);
        }

        $result = [];
        foreach ($input as $key => $raw) {
            $result[$key] = $raw;
        }
        $result['action'] = $existing ? 'Update' : 'Create';
        $result['errors'] = $errors;
        $result['saved'] = false;

        if (empty($errors) && $save) {
            try {
                DB::transaction(fn () => $this->saveEmployee($existing, $values));
                $result['saved'] = true;
            } catch (\Throwable $e) {
                $result['errors'][] = 'Save failed: '.$e->getMessage();
            }
        }

        return $result;
    }

    private function saveEmployee(?HrEmployee $employee, array $values): void
    {
        $isNew = !$employee;
        $employee ??= new HrEmployee();

        foreach (self::EMPLOYEE_COLUMNS as $column) {
            if (array_key_exists($column, $values)) {
                $employee->{$column} = $values[$column];
            }
        }
        $idColumns = [
            'classification' => 'classification_id', 'department' => 'department_id', 'section' => 'section_id',
            'sub_section' => 'sub_section_id', 'designation' => 'designation_id', 'working_place' => 'working_place_id',
            'shift' => 'shift_id', 'line' => 'floor_line_id',
        ];
        foreach ($idColumns as $key => $column) {
            if (array_key_exists($key, $values)) {
                $employee->{$column} = $values[$key];
            }
        }
        foreach (['comp_one', 'comp_two'] as $column) {
            if (array_key_exists($column, $values)) {
                $employee->{$column} = $values[$column];
            }
        }
        if (array_key_exists('status', $values)) {
            $employee->status = $this->isStatusNumeric() ? ($values['status'] === 'Active' ? 1 : 0) : strtolower($values['status']);
        } elseif ($isNew) {
            $employee->status = $this->isStatusNumeric() ? 1 : 'active';
        }

        $designation = $employee->designation_id ? HrDesignation::query()->find($employee->designation_id) : null;
        if ($designation && !empty($designation->grade)) {
            $employee->grade = $designation->grade;
        }

        if ($isNew) {
            $employee->created_by = Auth::id();
        }
        $employee->updated_by = Auth::id();
        $employee->save();

        // Basic info
        $basicValues = array_intersect_key($values, array_flip(self::BASIC_INFO_COLUMNS));
        $lookupBasic = array_filter([
            'marital_status_id' => $values['marital_status'] ?? null,
            'sex_id' => $values['gender'] ?? null,
            'religion_id' => $values['religion'] ?? null,
            'nationality_country_id' => $values['nationality'] ?? null,
            'payment_method_id' => $values['payment_method'] ?? null,
        ], fn ($v) => $v !== null);
        if ($basicValues || $lookupBasic) {
            $basic = $employee->basicInfo ?: new HrEmployeeBasicInfo(['employee_id' => $employee->id, 'status' => 1]);
            $basic->fill($basicValues + $lookupBasic);
            $basic->created_by ??= Auth::id();
            $basic->updated_by = Auth::id();
            $basic->save();
        }

        // Salary info
        $salaryValues = array_intersect_key($values, array_flip(self::SALARY_INFO_COLUMNS));
        if (array_key_exists('salary_effective_date', $values)) {
            $salaryValues['effective_date'] = $values['salary_effective_date'];
        }
        if (array_key_exists('payment_method', $values)) {
            $salaryValues['payment_method_id'] = $values['payment_method'];
        }
        if ($isNew && $designation) {
            foreach (self::DESIGNATION_SALARY_DEFAULTS as $column => $designationColumn) {
                $default = $designation->getAttribute($designationColumn);
                if (!array_key_exists($column, $salaryValues) && $default !== null && $default !== '') {
                    $salaryValues[$column] = $default;
                }
            }
        }
        if ($salaryValues) {
            $salary = $employee->salaryInfo ?: new HrEmployeeSalaryInfo(['employee_id' => $employee->id, 'status' => 1]);
            $salary->fill($salaryValues);
            $salary->created_by ??= Auth::id();
            $salary->updated_by = Auth::id();
            $salary->save();
        }

        // Permanent / present address
        foreach (['permanent', 'present'] as $type) {
            $addressValues = array_filter([
                'district_id' => $values[$type.'_district'] ?? null,
                'police_station_id' => $values[$type.'_police_station'] ?? null,
                'post_office' => $values[$type.'_post_office'] ?? null,
                'bn_post_office' => $values[$type.'_post_office_bn'] ?? null,
                'village' => $values[$type.'_village'] ?? null,
                'bn_village' => $values[$type.'_village_bn'] ?? null,
            ], fn ($v) => $v !== null);
            if (!$addressValues) {
                continue;
            }
            if (isset($addressValues['post_office'])) {
                $addressValues['post_office_id'] = $this->resolveLookup('post_office', $addressValues['post_office'], $addressValues['police_station_id'] ?? null, false, $ignored);
            }
            $address = HrEmployeeAddress::firstOrNew(['employee_id' => $employee->id, 'type' => $type]);
            $address->fill($addressValues);
            $address->status = 1;
            $address->created_by ??= Auth::id();
            $address->updated_by = Auth::id();
            $address->save();
        }

        // Nominee — SFL gives every new employee a nominee row with its standard
        // distribution, same as the Create Employee screen does.
        $nomineeValues = [];
        foreach (self::NOMINEE_COLUMNS as $column) {
            if (array_key_exists($this->nomineeKey($column), $values)) {
                $nomineeValues[$column] = $values[$this->nomineeKey($column)];
            }
        }
        foreach (['district' => 'district_id', 'police_station' => 'police_station_id'] as $key => $column) {
            if (array_key_exists('nominee_'.$key, $values)) {
                $nomineeValues[$column] = $values['nominee_'.$key];
            }
        }
        $isSfl = $isNew && function_exists('general') && optional(general())->company_s_code == 'SFL';
        if ($nomineeValues || $isSfl) {
            $nominee = $employee->nomineeRecord ?: new HrEmployeeNominee(['employee_id' => $employee->id, 'name' => '', 'status' => 1]);
            if ($isSfl) {
                $nomineeValues += [
                    'net_payment' => 100, 'provident_fund' => 0, 'insurance' => 100,
                    'accident_fine' => 100, 'profit' => 0, 'others' => 0,
                ];
            }
            $nominee->fill($nomineeValues);
            $nominee->save();
        }

        // Age verification
        $avValues = array_filter([
            'physical_ability' => $values['av_physical_ability'] ?? null,
            'physical_ability_bn' => $values['av_physical_ability_bn'] ?? null,
            'identification_mark' => $values['av_identification_mark'] ?? null,
            'identification_mark_bn' => $values['av_identification_mark_bn'] ?? null,
            'age_years' => $values['av_age_years'] ?? null,
            'verified_date' => $values['av_verified_date'] ?? null,
        ], fn ($v) => $v !== null);
        if ($avValues) {
            $av = $employee->ageVerification ?: new HrEmployeeAgeVerification(['employee_id' => $employee->id, 'status' => 1]);
            $av->fill($avValues);
            $av->save();
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function importableKeys(): array
    {
        return array_values(array_map(
            fn ($column) => $column[0],
            array_filter(self::COLUMNS, fn ($column) => $column[3] !== 'readonly')
        ));
    }

    private function nomineeKey(string $column): string
    {
        return 'nominee_'.$column;
    }

    private function parseValue(array $column, string $raw, ?string &$error): mixed
    {
        $error = null;
        $type = $column[3];

        switch ($type) {
            case 'text':
                $max = self::MAX_LENGTHS[$column[0]] ?? null;
                if ($max && mb_strlen($raw) > $max) {
                    $error = "must be at most {$max} characters.";
                }

                return $raw;

            case 'date':
                $date = $this->parseDate($raw);
                if (!$date) {
                    $error = "'{$raw}' is not a valid date (use YYYY-MM-DD or DD/MM/YYYY).";

                    return null;
                }

                return $date->toDateString();

            case 'number':
            case 'percent':
                $clean = $this->cleanNumber($raw);
                if (!is_numeric($clean) || (float) $clean < 0) {
                    $error = "'{$raw}' is not a valid number.";

                    return null;
                }
                if ($type === 'percent' && (float) $clean > 100) {
                    $error = "'{$raw}' must be between 0 and 100.";

                    return null;
                }

                return (float) $clean;

            case 'int':
                if (!preg_match('/^\d+$/', $this->cleanNumber($raw))) {
                    $error = "'{$raw}' must be a whole number.";

                    return null;
                }

                return (int) $this->cleanNumber($raw);

            case 'bool':
                $normalized = strtolower($raw);
                if (in_array($normalized, ['yes', 'y', '1', 'true', 'active'], true)) {
                    return 1;
                }
                if (in_array($normalized, ['no', 'n', '0', 'false', 'inactive'], true)) {
                    return 0;
                }
                $error = "'{$raw}' must be Yes or No.";

                return null;

            case 'enum':
                $needle = $this->normalizeEnum($raw);
                foreach ($column[4] as $option) {
                    if ($this->normalizeEnum($option) === $needle) {
                        return $option;
                    }
                }
                $error = "'{$raw}' must be one of: ".implode(', ', $column[4]).'.';

                return null;
        }

        return $raw;
    }

    private function normalizeEnum(string $value): string
    {
        return (string) preg_replace('/[\s_\-]+/', '_', strtolower(trim($value)));
    }

    /**
     * Matches $value against a lookup by name / Bangla name / code (case-insensitive).
     * $parentId narrows duplicates (e.g. two "Sadar" police stations in different
     * districts); with $strict a value that exists only under another parent is an error.
     */
    private function resolveLookup(string $lookup, string $value, ?int $parentId, bool $strict, ?string &$error): ?int
    {
        $error = null;
        $needle = mb_strtolower(trim($value));
        $matches = array_values(array_filter($this->lookup($lookup), fn ($item) => in_array($needle, $item['names'], true)));

        if (!$matches) {
            $error = "'{$value}' was not found.";

            return null;
        }

        if ($parentId) {
            $underParent = array_values(array_filter($matches, fn ($item) => $item['parent'] === $parentId));
            if ($underParent) {
                $matches = $underParent;
            } elseif ($strict) {
                $error = "'{$value}' does not belong to the selected parent (Department/Section).";

                return null;
            }
        }

        if (count($matches) > 1) {
            $error = "'{$value}' matches more than one record; please make it unique in the software first.";

            return null;
        }

        return $matches[0]['id'];
    }

    private function lookupLabel(string $lookup, $id): ?string
    {
        if (!$id) {
            return null;
        }
        foreach ($this->lookup($lookup) as $item) {
            if ($item['id'] === (int) $id) {
                return $item['label'];
            }
        }

        return null;
    }

    private function lookup(string $lookup): array
    {
        if (isset($this->lookups[$lookup])) {
            return $this->lookups[$lookup];
        }

        $simple = fn (string $model, ?string $parentColumn = null) => $model::query()->get()->map(fn ($row) => [
            'id' => (int) $row->id,
            'label' => (string) $row->name,
            'names' => array_values(array_unique(array_filter(array_map(
                fn ($v) => mb_strtolower(trim((string) $v)),
                [$row->name, $row->bn_name ?? null, $row->code ?? null]
            )))),
            'parent' => $parentColumn && $row->{$parentColumn} ? (int) $row->{$parentColumn} : null,
        ])->all();

        $geo = fn (string $type) => HrGeoLocation::query()->where('type', $type)->get()->map(fn ($row) => [
            'id' => (int) $row->id,
            'label' => (string) $row->name,
            'names' => array_values(array_unique(array_filter(array_map(
                fn ($v) => mb_strtolower(trim((string) $v)),
                [$row->name, $row->bn_name]
            )))),
            'parent' => $row->parent_id ? (int) $row->parent_id : null,
        ])->all();

        return $this->lookups[$lookup] = match ($lookup) {
            'classification' => $simple(HrClassification::class),
            'department' => $simple(HrDepartment::class),
            'section' => $simple(HrSection::class, 'department_id'),
            'sub_section' => $simple(HrSubSection::class, 'section_id'),
            'designation' => $simple(HrDesignation::class, 'department_id'),
            'working_place' => $simple(HrWorkingPlace::class),
            'shift' => $simple(HrShift::class),
            'marital_status' => $simple(HrMaritalStatus::class),
            'gender' => $simple(HrSex::class),
            'religion' => $simple(HrReligion::class),
            'payment_method' => $simple(HrPaymentMethod::class),
            'line' => HrFloorLine::query()->get()->map(fn ($row) => [
                'id' => (int) $row->id,
                'label' => (string) $row->line_name,
                'names' => array_values(array_unique(array_filter(array_map(
                    fn ($v) => mb_strtolower(trim((string) $v)),
                    [$row->line_name, $row->bn_line_name]
                )))),
                'parent' => null,
            ])->all(),
            'country', 'district', 'police_station', 'post_office' => $geo($lookup),
        };
    }

    private function isStatusNumeric(): bool
    {
        if ($this->numericStatus === null) {
            $type = strtolower((string) Schema::getColumnType((new HrEmployee())->getTable(), 'status'));
            $this->numericStatus = in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'boolean'], true);
        }

        return $this->numericStatus;
    }

    private function findSheet(Spreadsheet $spreadsheet): Worksheet
    {
        foreach ($spreadsheet->getSheetNames() as $name) {
            if (strcasecmp($name, self::SHEET_NAME) === 0) {
                return $spreadsheet->getSheetByName($name);
            }
        }

        return $spreadsheet->getSheet(0);
    }

    private function normalizeHeading($heading): string
    {
        $text = mb_strtolower(trim((string) $heading));
        $text = rtrim($text, " *");

        return (string) preg_replace('/\s+/', ' ', $text);
    }

    /**
     * Raw cell value -> string. Whole floats lose their ".0" (an ID or phone typed as
     * a number comes back as 1712345678.0); numeric date cells become YYYY-MM-DD.
     */
    private function cellToString($value, bool $isDate): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if ($isDate && is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            } catch (\Throwable) {
                return (string) $value;
            }
        }
        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return sprintf('%.0f', $value);
        }

        return trim((string) $value);
    }

    private function cleanNumber(string $value): string
    {
        if (is_numeric($value)) {
            return $value;
        }

        $clean = preg_replace('/[^\d.\-]/', '', $value);

        return $clean === '' || $clean === '-' ? '' : $clean;
    }

    private function parseDate(string $value): ?Carbon
    {
        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            } catch (\Throwable) {
                return null;
            }
        }

        // Day-first formats are tried before Carbon::parse(), which would read
        // 05/06/2024 as May 6th instead of 5 June.
        foreach (['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'Y/m/d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date;
                }
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatDate($value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
