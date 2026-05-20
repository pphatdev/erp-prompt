<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Application;
use App\Models\Tenant\Quiz;
use App\Tenants\Modules\HRM\Requests\AssignQuizRequest;
use App\Tenants\Modules\HRM\Requests\StoreQuizQuestionRequest;
use App\Tenants\Modules\HRM\Requests\StoreQuizRequest;
use App\Tenants\Modules\HRM\Resources\QuizAttemptResource;
use App\Tenants\Modules\HRM\Resources\QuizResource;
use App\Tenants\Modules\HRM\Services\QuizService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class QuizController extends Controller
{
    use Paginates;

    public function __construct(private readonly QuizService $quizzes)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quiz::class);

        $paginator = $this->paginateQuery(
            Quiz::query()->with('questions')->orderBy('title'),
            $request
        );

        return $this->paginatedResponse(QuizResource::class, $paginator, $request);
    }

    public function store(StoreQuizRequest $request): QuizResource
    {
        $this->authorize('create', Quiz::class);

        return new QuizResource($this->quizzes->createQuiz($request->validated()));
    }

    public function show(Quiz $quiz): QuizResource
    {
        $this->authorize('view', $quiz);

        return new QuizResource($quiz->load('questions'));
    }

    public function update(StoreQuizRequest $request, Quiz $quiz): QuizResource
    {
        $this->authorize('update', $quiz);

        return new QuizResource($this->quizzes->updateQuiz($quiz, $request->validated()));
    }

    public function destroy(Quiz $quiz): JsonResponse
    {
        $this->authorize('delete', $quiz);

        $quiz->delete();

        return response()->json(['message' => 'Quiz archived.'], 200);
    }

    public function addQuestion(StoreQuizQuestionRequest $request, Quiz $quiz): JsonResponse
    {
        $this->authorize('update', $quiz);

        $question = $this->quizzes->createQuestion($quiz, $request->validated());

        return response()->json(['data' => ['id' => $question->id]], 201);
    }

    /**
     * Assign a quiz to a recruitment Application. Returns the raw token + the
     * candidate magic-link URL ONCE — subsequent reads of the attempt only
     * surface the hash.
     */
    public function assignToApplication(AssignQuizRequest $request, Application $application): JsonResponse
    {
        $quiz = Quiz::findOrFail($request->validated()['quiz_id']);
        $this->authorize('update', $quiz);

        try {
            $result = $this->quizzes->assignToApplication(
                $quiz,
                $application,
                (int) ($request->validated()['expires_in_hours'] ?? 72),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $token = $result['token'];
        $candidateUrl = URL::query(config('app.frontend_url', config('app.url')) . '/candidate/quiz', [
            'token' => $token,
        ]);

        return response()->json([
            'data' => [
                'attempt'     => new QuizAttemptResource($result['attempt']),
                'token'       => $token,
                'candidateUrl'=> $candidateUrl,
            ],
        ], 201);
    }
}
