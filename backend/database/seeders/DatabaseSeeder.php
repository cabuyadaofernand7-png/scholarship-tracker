<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\DocumentRequirement;
use App\Models\Scholarship;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Fake, local-only accounts (example.test is a reserved fake domain)
        foreach ([
            ['Admin Demo', 'admin@example.test', 'admin'],
            ['Staff One', 'staff1@example.test', 'staff'],
            ['Staff Two', 'staff2@example.test', 'staff'],
            ['Reviewer One', 'reviewer1@example.test', 'reviewer'],
            ['Reviewer Two', 'reviewer2@example.test', 'reviewer'],
        ] as [$name, $email, $role]) {
            User::factory()->create(['name' => $name, 'email' => $email, 'role' => $role]);
        }

        // Deadlines 7, 3, and 1 day from today so one run shows all three reminders
        $scholarships = [
            Scholarship::create(['name' => 'Academic Excellence Scholarship', 'scholarship_type' => 'academic', 'deadline_date' => now()->addDays(7)->toDateString()]),
            Scholarship::create(['name' => 'Financial Assistance Grant', 'scholarship_type' => 'financial_aid', 'deadline_date' => now()->addDays(3)->toDateString()]),
            Scholarship::create(['name' => 'Leadership Scholarship', 'scholarship_type' => 'leadership', 'deadline_date' => now()->addDays(1)->toDateString()]),
        ];

        foreach ($scholarships as $s) {
            foreach (['Application Form', 'Certificate of Registration'] as $docName) {
                DocumentRequirement::create(['scholarship_id' => $s->id, 'name' => $docName]);
            }
            DocumentRequirement::create(['scholarship_id' => $s->id, 'name' => 'Previous Semester Grade Report', 'applicant_type' => 'renewing']);
            DocumentRequirement::create(['scholarship_id' => $s->id, 'name' => 'High School Report Card', 'year_level' => 1, 'applicant_type' => 'new']);
        }
        DocumentRequirement::create(['scholarship_id' => $scholarships[1]->id, 'name' => 'Proof of Family Income']);

        $students = [
            ['Student One', 'student1@example.test', '2026-0001', 1, 'BSIT', 'new'],
            ['Student Two', 'student2@example.test', '2026-0002', 2, 'BSIT', 'renewing'],
            ['Student Three', 'student3@example.test', '2026-0003', 3, 'BSCS', 'renewing'],
            ['Student Four', 'student4@example.test', '2026-0004', 1, 'BSED', 'new'],
            ['Student Five', 'student5@example.test', '2026-0005', 2, 'BSCS', 'renewing'],
            // Invalid email on purpose: used for the permanent-failure demo
            ['Student Six', 'invalid-email-address', '2026-0006', 4, 'BSIT', 'renewing'],
        ];

        foreach ($students as $i => [$name, $email, $number, $year, $program, $type]) {
            $user = User::factory()->create(['name' => $name, 'email' => $email, 'role' => 'student']);
            $applicant = Applicant::create([
                'user_id' => $user->id,
                'student_number' => $number,
                'year_level' => $year,
                'program' => $program,
                'applicant_type' => $type,
            ]);

            $scholarship = $scholarships[intdiv($i, 2)];
            $application = Application::create([
                'applicant_id' => $applicant->id,
                'scholarship_id' => $scholarship->id,
                'status' => 'submitted',
                'last_activity_at' => now(),
            ]);

            $requirements = DocumentRequirement::where('scholarship_id', $scholarship->id)
                ->get()
                ->filter(fn ($r) => $r->appliesTo($applicant))
                ->values();

            foreach ($requirements as $j => $req) {
                $status = $j === 0 ? 'approved' : 'pending'; // pending = not yet uploaded
                $remark = null;
                if ($i === 1 && $j === 1) {
                    $status = 'rejected';
                    $remark = 'Blurry copy, please upload a clearer scan.';
                }
                Submission::create([
                    'application_id' => $application->id,
                    'document_requirement_id' => $req->id,
                    'status' => $status,
                    'rejection_remark' => $remark,
                ]);
            }
        }
    }
}
