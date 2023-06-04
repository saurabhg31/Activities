<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RequestLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function App\Helpers\translate;

class LogsController extends Controller
{
    private $logTable;

    public function __construct()
    {
        if (empty(env('LOG_API_ACCESS_KEY'))) {
            throw new HttpException(
                500,
                translate('messages.errors.log_access_key_not_set')
            );
        }
        if (!request()->hasHeader('Log-Api-Access-Key') || !Hash::check(request()->header('Log-Api-Access-Key'), env('LOG_API_ACCESS_KEY'))) {
            throw new HttpException(
                403,
                translate('messages.errors.invalid_log_access_key')
            );
        }
        $this->logTable = new RequestLog();
    }

    /**
     * Read request resource consumption log
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function readRequestLogs(Request $request)
    {
        $query = $this->logTable->select($request->fields);
        if (!empty($request->route)) {
            $query->whereIn('route', $request->route);
        }
        if (!empty($request->ignore_route)) {
            $query->whereNotIn('route', $request->ignore_route);
        }
        if (!empty($request->method)) {
            $query->whereIn('method', $request->method);
        }
        if (!empty($request->status_code)) {
            $query->whereIn('status_code', $request->status_code);
        }
        if (!empty($request->search)) {
            $query->where(function ($conditions) use ($request) {
                foreach ($request->search as $search) {
                    if ($search['operator'] == 'in' || $search['operator'] == 'not in') {
                        if (!is_array($search['value'])) {
                            throw new HttpException(422, translate('messages.errors.value_must_be_array_when_operator_in'));
                        }
                    }
                    if (isset($search['successful']) && $search['successful'] !== 'ignore') {
                        $conditions->where('successful', ($search['successful'] == 'yes' ? 1 : 0));
                    }
                    $conditions->where($search['column'], $search['operator'], $search['value']);
                }
            });
        }
        if (!empty($request->sort)) {
            foreach ($request->sort as $sort) {
                $query->orderBy($sort['column'], $sort['order']);
            }
        }
        $totalLogCount = $query->count();
        if ($request->paginate) {
            $query->offset(($request->paginate['page'] - 1) * $request->paginate['per_page'])->limit($request->paginate['per_page']);
        }
        $queryString = vsprintf(
            str_replace('?', "'%s'", $query->toSql()),
            array_map(function ($value) {
                if (is_bool($value)) {
                    return (int)$value;
                }
                return $value;
            }, $query->getBindings())
        );
        return response()->json([
            'total' => $totalLogCount,
            'logs' => $query->get(),
            'log_search_query' => $queryString
        ]);
    }

    /**
     * Clear cache for backend devs
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearCache()
    {
        Artisan::call('cache:clear');
        return response()->json([
            'result' => true
        ]);
    }

    /**
     * Read or download laravel log file
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function readOrDownloadLogFile(Request $request)
    {
        $logDirectory = storage_path('logs');
        $files = array_filter(scandir($logDirectory), function ($fileName) {
            return !in_array($fileName, ['.', '..', '.gitignore']);
        });
        $request->validate([
            'download' => 'nullable|boolean|required_with:file',
            'file' => 'nullable|string|in:' . implode(',', $files)
        ]);
        if (!$request->has('file') || empty($request->get('file'))) {
            return response()->json([
                'present_log_files' => array_values($files)
            ]);
        }
        $logFile = $logDirectory . '/' . $request->file;
        if ($request->download) {
            return response()->download(
                $logFile,
                $request->file . '_' . env('APP_ENV') . 'Environment_' . now()->format('Ymd_Hi_T') . '.log'
            );
        } else {
            return response()->json([
                'laravel_log_data' => file_get_contents($logFile)
            ]);
        }
    }
}
