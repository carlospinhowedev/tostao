<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Rules\OwnerChoice;
use App\Services\CsvImporter;
use App\Services\ImportService;
use App\Support\DateParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Three steps: upload the bank file, check the column mapping against a live
 * preview, then pick which rows to keep. The file lives in storage until the
 * import is confirmed, so nothing has to be uploaded twice.
 */
class ImportController extends Controller
{
    public function __construct(private CsvImporter $importer, private ImportService $imports) {}

    public function create(): View
    {
        return view('imports.create', ['accounts' => Account::where('archived', false)->orderBy('name')->get()]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'file' => ['required', 'file', 'max:5120', 'extensions:csv,txt,tsv'],
        ]);

        $token = (string) Str::uuid();
        Storage::disk('local')->put("imports/{$token}.csv", $request->file('file')->get());

        return redirect()->route('imports.review', ['token' => $token, 'account_id' => $request->account_id]);
    }

    public function review(Request $request, string $token): View
    {
        $text = $this->text($token);
        $account = Account::findOrFail($request->query('account_id'));
        $settings = $this->settings($request, $text);

        $result = $this->importer->parse($text, $settings['delimiter'], $settings['has_header'], $settings['map'], $settings['date_format'], $settings['decimal']);

        return view('imports.review', [
            'token' => $token,
            'account' => $account,
            'settings' => $settings,
            'header' => $settings['columns'],
            'preview' => $this->imports->prepare($account, $result['rows']),
            'skipped' => $result['skipped'],
            'categories' => Category::orderBy('name')->get(),
            'members' => User::orderBy('id')->get(),
        ]);
    }

    public function commit(Request $request, string $token): RedirectResponse
    {
        $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*' => ['integer'],
            'category.*' => ['nullable', 'exists:categories,id'],
            'owner.*' => ['nullable', new OwnerChoice(allowEmpty: true)],
        ], ['rows.required' => __('Select at least one row to import.')]);

        $text = $this->text($token);
        $account = Account::findOrFail($request->account_id);
        $settings = $this->settings($request, $text);

        $result = $this->importer->parse($text, $settings['delimiter'], $settings['has_header'], $settings['map'], $settings['date_format'], $settings['decimal']);
        $selected = array_map('intval', $request->input('rows'));

        $items = [];
        foreach ($this->imports->prepare($account, $result['rows']) as $row) {
            if (! in_array($row['line'], $selected, true)) {
                continue;
            }
            $category = $request->input("category.{$row['line']}");
            $owner = $request->input("owner.{$row['line']}");

            $row['category_id'] = $category ? (int) $category : null;
            $row['owner_id'] = $owner && $owner !== 'shared' ? (int) $owner : null;
            $items[] = $row;
        }

        $count = $this->imports->commit($account, $items);
        Storage::disk('local')->delete("imports/{$token}.csv");

        return redirect()->route('transactions.index')->with('status', trans_choice(':count transaction imported.|:count transactions imported.', $count, ['count' => $count]));
    }

    private function text(string $token): string
    {
        abort_unless(Str::isUuid($token) && Storage::disk('local')->exists("imports/{$token}.csv"), 404);

        return $this->importer->normalise(Storage::disk('local')->get("imports/{$token}.csv"));
    }

    /**
     * Mapping from the query string, falling back to what we can detect.
     *
     * @return array<string, mixed>
     */
    private function settings(Request $request, string $text): array
    {
        $delimiter = $request->input('delimiter');
        $delimiter = $delimiter === 'tab' ? "\t" : $delimiter;
        $delimiter = array_key_exists((string) $delimiter, CsvImporter::DELIMITERS) ? $delimiter : $this->importer->detectDelimiter($text);

        $rows = array_values($this->importer->readRows($text, $delimiter));
        $hasHeader = $request->has('delimiter') ? $request->boolean('has_header') : $this->looksLikeHeader($rows[0] ?? []);
        $columns = $hasHeader ? ($rows[0] ?? []) : array_map(fn ($i) => __('Column').' '.($i + 1), array_keys($rows[0] ?? []));

        $guess = $hasHeader ? $this->importer->guessMapping($rows[0] ?? []) : ['date' => 0, 'description' => 1, 'amount' => 2, 'debit' => null, 'credit' => null];

        $pick = fn (string $key) => $request->has('delimiter')
            ? ($request->input($key) === null || $request->input($key) === '' ? null : (int) $request->input($key))
            : $guess[$key];

        $map = ['date' => $pick('date'), 'description' => $pick('description'), 'amount' => $pick('amount'), 'debit' => $pick('debit'), 'credit' => $pick('credit')];

        $samples = array_filter(array_map(fn ($r) => $r[$map['date'] ?? 0] ?? null, array_slice($rows, $hasHeader ? 1 : 0, 15)));
        $dateFormat = $request->input('date_format');
        $dateFormat = array_key_exists((string) $dateFormat, DateParser::FORMATS) ? $dateFormat : DateParser::guessFormat($samples);

        $decimal = in_array($request->input('decimal'), ['auto', 'comma', 'dot'], true) ? $request->input('decimal') : 'auto';

        return [
            'delimiter' => $delimiter,
            'has_header' => $hasHeader,
            'columns' => $columns,
            'map' => $map,
            'date_format' => $dateFormat,
            'decimal' => $decimal,
        ];
    }

    /** A first row with no digit-heavy cells is almost certainly a header. */
    private function looksLikeHeader(array $row): bool
    {
        foreach ($row as $cell) {
            if (preg_match('/\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4}|\d+[.,]\d{2}/', $cell)) {
                return false;
            }
        }

        return $row !== [];
    }
}
