<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Mail;
use App\Models\EmailTemplate;

class EmailTemplateController extends Controller
{
    public function index()
    {
        $template = EmailTemplate::where('identifier', 'register_mail')->first()->toArray();
        return view('admin.email_templates.create', compact('template'));
    }

    public function create()
    {
        return view('admin.email_templates.create');
    }

   public function store(Request $request)
{
    $request->validate([
        'subject' => 'required',
        'body' => 'required',
    ]);

    // Use a unique key to identify the template, e.g., "register_mail"
    $key = 'register_mail';

    // Check if template exists
    $template = EmailTemplate::where('identifier', $key)->first();

    if ($template) {
        // Update existing template
        $template->update([
            'subject' => $request->subject,
            'body' => $request->body,
        ]);
    } else {
        // Create new template
        EmailTemplate::create([
            'identifier' => $key,
            'subject' => $request->subject,
            'body' => $request->body,
        ]);
    }

    return redirect()->route('admin.email-templates.index')->with('success', 'Template saved.');
}

}
