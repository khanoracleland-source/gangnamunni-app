<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\PatientCase;
use App\Repositories\DocumentRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DocumentAPIController extends AppBaseController
{
    /** @var DocumentRepository */
    private $documentRepository;

    public function __construct(DocumentRepository $documentRepo)
    {
        $this->documentRepository = $documentRepo;
    }

    /**
     * Document lookup bound to the caller's tenant and, for patients, to their own patient record.
     * The Sanctum API group never initializes tenancy, so the BelongsToTenant global scope is
     * inert here and every by-id lookup must scope explicitly.
     */
    private function findScopedDocument(int|string $id): ?Document
    {
        $query = Document::where('id', $id)->where('tenant_id', getLoggedInUser()->tenant_id);
        if (getLoggedinPatient()) {
            $query->where('patient_id', getLoggedInUser()->owner_id);
        }

        return $query->first();
    }

    public function index(): \Illuminate\Http\JsonResponse
    {
        if (! getLoggedinPatient()) {
            $documents = Document::whereHas('patient.patientUser')->where('tenant_id',getLoggedInUser()->tenant_id)->with('documentType', 'patient.patientUser',
                'media')->orderBy('id', 'desc')->get();
        } else {
            $patientId = Patient::where('user_id', getLoggedInUserId())->first();
            $documents = Document::whereHas('patient.patientUser')->where('tenant_id',getLoggedInUser()->tenant_id)->with('documentType', 'patient.patientUser',
                'media')->orderBy('id', 'desc')->select('documents.*')->where('patient_id', $patientId->id)->get();
        }
        $data = [];
        foreach ($documents as $document) {
            $data[] = $document->prepareDocument();
        }

        return $this->sendResponse($data, 'Document Retrieved Successfully');
    }

    public function getDocumentTypes(): \Illuminate\Http\JsonResponse
    {
        $document_type = DocumentType::select(['id', 'name'])->where('tenant_id',getLoggedInUser()->tenant_id)->orderBy('id', 'desc')->get();

        return $this->sendResponse($document_type, 'Document Retrieved Successfully');
    }

    public function create(Request $request): \Illuminate\Http\JsonResponse
    {
        $input = $request->all();
        $request->validate([
            'title' => 'required',
            'document_type_id' => 'required|gt:0',
        ]);
        $input['tenant_id'] = getLoggedInUser()->tenant_id;
        $this->documentRepository->store($input);

        return $this->sendSuccess(__('messages.ipd_patient_timeline.document').' '.__('messages.common.saved_successfully'));
    }

    public function update(Request $request, int|string $id): \Illuminate\Http\JsonResponse
    {
        $input = $request->all();
        $request->validate([
            'title' => 'required',
            'document_type_id' => 'required|gt:0',
        ]);
        if (getLoggedinPatient()) {
            $input['patient_id'] = getLoggedInUser()->owner_id;
        }
        // Callers may not re-home a document into another tenant.
        unset($input['tenant_id']);
        $documents = $this->findScopedDocument($id);
        if (! $documents) {
            return $this->sendError(__('messages.ipd_patient_timeline.document').' '.__('messages.common.not_found'));
        }
        $this->documentRepository->updateDocument($input, $id);

        return $this->sendSuccess(__('messages.ipd_patient_timeline.document').' '.__('messages.common.updated_successfully'));
    }

    public function destroy(int|string $id): \Illuminate\Http\JsonResponse
    {
        $documents = $this->findScopedDocument($id);

        if (! $documents) {
            return $this->sendError(__('messages.ipd_patient_timeline.document').' '.__('messages.common.not_found'));
        }
        $this->documentRepository->deleteDocument($id);

        return $this->sendSuccess(__('messages.ipd_patient_timeline.document').' '.__('messages.common.deleted_successfully'));
    }

    public function downloadDocs(int|string $id): \Illuminate\Http\JsonResponse
    {
        $document = $this->findScopedDocument($id);
        if (! $document) {
            return $this->sendError(__('messages.ipd_patient_timeline.document').' '.__('messages.common.not_found'));
        }
        $documentMedia = $document->media[0] ?? null;
        if (! $documentMedia) {
            return $this->sendError(__('messages.ipd_patient_timeline.document').' '.__('messages.common.not_found'));
        }

        $url = $documentMedia->getUrl();

        return $this->sendResponse($url ?? '', 'Document download URL');
    }

    public function getPatientList(): \Illuminate\Http\JsonResponse
    {
        $patientCase = PatientCase::with('patient.patientUser')->where('doctor_id', '=',
            getLoggedInUser()->owner_id)->where('status', '=', 1)->get()->pluck('patient.user_id', 'id');

        $patientAdmission = PatientAdmission::with('patient.patientUser')->where('doctor_id', '=',
            getLoggedInUser()->owner_id)->where('status', '=', 1)->get()->pluck('patient.user_id', 'id');

        $arrayMerge = array_merge($patientAdmission->toArray(), $patientCase->toArray());
        $patientIds = array_unique($arrayMerge);

        $patients = Patient::with('patientUser')->whereIn('user_id', $patientIds)
            ->whereHas('patientUser', function (Builder $query) {
                $query->where('status', 1);
            })->get();

        $data = [];
        foreach ($patients as $patient) {
            $data[] = $patient->prepareData();
        }

        return $this->sendResponse($data, 'Document Retrieved Successfully');
    }

    public function edit(int|string $id): \Illuminate\Http\JsonResponse
    {
        if (getLoggedinPatient()) {
            $document = Document::where('id', $id)->where('patient_id', getLoggedInUser()->owner_id)->where('tenant_id',getLoggedInUser()->tenant_id)->first();
        } else {
            $document = Document::where('id', $id)->where('tenant_id',getLoggedInUser()->tenant_id)->first();
        }

        if (! $document) {
            return $this->sendError(__('messages.ipd_patient_timeline.document').' '.__('messages.common.not_found'));
        }

        return $this->sendResponse($document->prepareDocument(), 'Document Retrieved Successfully.');
    }
}
