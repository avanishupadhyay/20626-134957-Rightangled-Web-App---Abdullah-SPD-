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
        $template = EmailTemplate::all()->toArray();
        return view('admin.email_templates.list', compact('template'));
    }

    public function create($key = "")
    {
        $template = '';
        if (isset($key) && !empty($key)) {
            $template = EmailTemplate::where('identifier', $key)->first()->toArray();
        }

        return view('admin.email_templates.create', compact('template'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'identifier' => 'required|unique:email_templates,identifier',
            'subject' => 'required',
            'body' => 'required',
        ]);

        EmailTemplate::create([
            'identifier' => $request->identifier,
            'subject' => $request->subject,
            'body' => $request->body,
        ]);


        return redirect()->route('admin.email-templates.index')->with('success', 'Template saved.');
    }

    public function update(Request $request, $key = "")
    {
        $request->validate([
            'identifier' => 'required',
            'subject' => 'required',
            'body' => 'required',
        ]);

        $template = EmailTemplate::where('identifier', $key)->firstOrFail();

        // Check if the new identifier already exists for another template
        $existing = EmailTemplate::where('identifier', $request->identifier)
            ->where('id', '!=', $template->id)
            ->first();

        if ($existing) {
            return redirect()->back()->withErrors(['identifier' => 'Key already exists.'])->withInput();
        }

        $template->update([
            'identifier' => $request->identifier,
            'subject' => $request->subject,
            'body' => $request->body,
        ]);

        return redirect()->route('admin.email-templates.index')->with('success', 'Template Updated Succesfully.');
    }
}
