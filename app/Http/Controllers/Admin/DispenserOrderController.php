<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use App\Models\AuditLog;
use App\Models\OrderAction;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\OrderDispense;
use App\Models\DispenseBatch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;



class DispenserOrderController extends Controller
{


    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->check() || !auth()->user()->hasRole('Dispenser')) {
                abort(403, 'Access denied');
            }
            return $next($request);
        }); // <- This line skips index()
    }


    public function index(Request $request)
    {
        $approvedOrderNumbers = OrderAction::where('decision_status', 'approved')
            ->latest('created_at')
            ->pluck('order_id');

        $alreadyDispensed = OrderDispense::pluck('order_id')->toArray();

        $query = Order::whereIn('order_number', $approvedOrderNumbers)
            ->whereNotIn('order_number', $alreadyDispensed);


        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%')
                    ->orWhere('order_number', 'like', '%' . $request->search . '%');
            });
        }

        $orders = $query->latest()->paginate(config('Reading.nodes_per_page'));

        $orders->getCollection()->transform(function ($order) {
            // Ensure order_data is array (decode if it's string)
            $orderData = is_array($order->order_data)
                ? $order->order_data
                : json_decode($order->order_data, true);

            $lineItems = collect($orderData['line_items'] ?? []);
            $order->total_quantity = $lineItems->sum('current_quantity');
            // dd($order->total_quantity );
            $order->line_items_titles = $lineItems->pluck('title')->toArray();
            // dd( $order->line_items_titles);
            $order->decoded_order_data = $orderData; // store for blade if needed
            return $order;
        });

        return view('admin.dispenser.index', compact('orders'));
    }

    public function view($id)
    {
        $order = Order::findOrFail($id);
        $orderMetafields = getOrderMetafields($order->order_number) ?? null;

        $orderData = json_decode($order->order_data, true);

        return view('admin.dispenser.view', compact('order', 'orderData', 'orderMetafields'));
    }


    public function printDispenseBatch(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
        ]);

        $orderNumbers = $request->order_ids;

        // Fetch orders
        $orders = Order::whereIn('order_number', $orderNumbers)->get();

        // Process and sort orders
        $processedOrders = $orders->map(function ($order) {
            $orderData = json_decode($order->order_data, true);
            $lineItems = collect($orderData['line_items'] ?? [])->map(function ($item) {
                $productId = $item['product_id'] ?? null;
                $item['direction_of_use'] = $productId ? getProductMetafield($productId) : 'N/A';
                return $item;
            })
                ->sortByDesc('quantity') // Highest quantity comes first
                ->values();

            $order->order_data = $orderData;
            $order->line_items = $lineItems;
            $order->total_quantity = $lineItems->sum('quantity');

            return $order;
        })
            ->sortByDesc('total_quantity') // Orders with more total quantity come first
            ->values();
        // Create Dispense Batch
        $batch = DispenseBatch::create([
            'batch_number' => 'BATCH-' . now()->format('YmdHis') . '-' . Str::random(4),
            'user_id' => auth()->id(),
        ]);

        // Generate PDF
        $pdfHtml = view('admin.dispenser.dispenselabel', compact('processedOrders', 'batch'))->render();
        $pdf = PDF::loadHTML($pdfHtml)->setPaper('A4');


        $fileName = "{$batch->batch_number}.pdf";
        $filePath = "dispense_batches/{$fileName}";

        Storage::disk('public')->put($filePath, $pdf->output());
        $batch->update(['pdf_path' => $filePath]);

        // $batch->update(['pdf_path' => "storage/{$pdfFileName}"]);

        // Log in OrderDispense
        foreach ($processedOrders as $order) {
            OrderDispense::create([
                'order_id' => $order->order_number,
                'batch_id' => $batch->id,
                'dispensed_at' => now(),
                'reprint_count' => 0,
            ]);
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'dispensed',
                'order_id' => $order->order_number,
                'details' => 'Order dispensed by ' . auth()->user()->name . ' on ' . now()->format('d/m/Y') . ' at ' . now()->format('H:i'),
            ]);
        }

        // Step 4: Log


        return redirect()->route('dispenser_orders.index')->with('success', 'Dispensing PDF generated and saved.');
    }

    public function showQrData()
    {
        return QrCode::generate(
            'Hello, World!',
        );
    }

    public function listBatches(Request $request)
    {
        $query = DispenseBatch::query();

        if ($request->filled('search')) {
            $query->where('batch_number', 'like', '%' . $request->search . '%');
        }

        $batches = $query->latest()->paginate(config('Reading.nodes_per_page'));

        return view('admin.dispenser.batches.list', compact('batches'));
    }

}
