<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coins;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeedKeywordRequest;
use App\Services\Coin\FeedKeywordService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class FeedKeywordController
 *
 * Handles creation of feed keywords and associated tags.
 */
class FeedKeywordController extends Controller
{
    /**
     * FeedKeywordController constructor.
     */
    public function __construct(
        protected FeedKeywordService $keywordService
    ) {}

    /**
     * Show form to create a new feed keyword.
     */
    public function index(): View
    {
        $keywords = $this->keywordService->getAllWithTags();

        return view('coins.keywords.index', compact('keywords'));
    }

    /**
     * Store a new feed keyword with optional tags.
     *
     * @param  StoreFeedKeywordRequest  $request  Validated request instance.
     */
    public function store(StoreFeedKeywordRequest $request): RedirectResponse
    {
        try {
            $this->keywordService->create($request->validated());

            return redirect()
                ->route('coins.feed-keywords.index')
                ->with('success', 'Keyword created successfully.');
        } catch (QueryException $e) {
            // Duplicate entry error
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'This keyword already exists.');
            }

            Log::error('Database error on keyword creation: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Database error. Please try again later.');
        } catch (Exception $e) {
            Log::error('Failed to create keyword: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create keyword. Please try again later.');
        }
    }

    /**
     * Delete a feed keyword and its tags.
     *
     * @param  int  $id  Feed keyword identifier.
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $this->keywordService->delete($id);

            return redirect()->back()->with('success', 'Keyword and its tags deleted successfully.');
        } catch (Throwable $e) {
            Log::error('Delete keyword failed: '.$e->getMessage(), [
                'keyword_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to delete keyword. Please try again.');
        }
    }
}
