import { useEffect, useRef, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { FileSignature, Upload } from 'lucide-react'

import {
  createEmployeeContract,
  signEmployeeContract,
  updateEmployeeContract,
  uploadEmployeeContractDocument,
} from '@/api/hr'
import { getApiErrorInfo } from '@/lib/api-errors'
import { downloadPdf } from '@/lib/pdf-download'
import { useAuthStore } from '@/lib/auth-store'
import type { Employee, EmployeeContract } from '@/types/hr'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { RichTextEditor } from '@/components/hr/RichTextEditor'
import { SignaturePad, type SignaturePadHandle } from '@/components/hr/SignaturePad'

type Step = 'choose' | 'details' | 'upload' | 'edit' | 'sign' | 'done'

interface ContractFlowDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  employee: Employee
  /** When resuming a draft/generated contract via "Continue" on the list. */
  resumeContract?: EmployeeContract | null
}

const getEmptyDetails = (currencyCode: string) => ({ contractType: '', startDate: '', endDate: '', baseSalary: '', currencyCode })

export function ContractFlowDialog({ open, onOpenChange, employee, resumeContract }: ContractFlowDialogProps) {
  const queryClient = useQueryClient()
  const companyCurrencyCode = useAuthStore((state) => state.company?.currency_code) ?? 'USD'

  const [step, setStep] = useState<Step>('choose')
  const [details, setDetails] = useState(() => getEmptyDetails(companyCurrencyCode))
  const [contract, setContract] = useState<EmployeeContract | null>(null)
  const [bodyDraft, setBodyDraft] = useState('')
  const [signedByName, setSignedByName] = useState('')
  const [uploadFile, setUploadFile] = useState<File | null>(null)
  const [uploadSignedByName, setUploadSignedByName] = useState('')
  const fileInputRef = useRef<HTMLInputElement>(null)
  const signaturePadRef = useRef<SignaturePadHandle>(null)

  useEffect(() => {
    if (!open) return
    if (resumeContract) {
      setContract(resumeContract)
      setBodyDraft(resumeContract.contract_body ?? '')
      setStep('edit')
    } else {
      setStep('choose')
      setDetails(getEmptyDetails(companyCurrencyCode))
      setContract(null)
      setBodyDraft('')
    }
    setSignedByName('')
    setUploadFile(null)
    setUploadSignedByName('')
  }, [open, resumeContract, companyCurrencyCode])

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['employee-contracts', employee.id] })

  const generateMutation = useMutation({
    mutationFn: () =>
      createEmployeeContract({
        employee_id: employee.id,
        contract_type: details.contractType,
        start_date: details.startDate,
        end_date: details.endDate || null,
        base_salary: Number(details.baseSalary),
        currency_code: details.currencyCode,
      }),
    onSuccess: (created) => {
      setContract(created)
      setBodyDraft(created.contract_body ?? '')
      setStep('edit')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const uploadMutation = useMutation({
    mutationFn: async () => {
      if (!uploadFile) throw new Error('Select a file to upload')
      const created = await createEmployeeContract({
        employee_id: employee.id,
        contract_type: details.contractType,
        start_date: details.startDate,
        end_date: details.endDate || null,
        base_salary: Number(details.baseSalary),
        currency_code: details.currencyCode,
      })
      return uploadEmployeeContractDocument(created.id, uploadFile, {
        isSignedPhysicalCopy: true,
        signedByName: uploadSignedByName || undefined,
      })
    },
    onSuccess: (result) => {
      setContract(result)
      setStep('done')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const finalizeMutation = useMutation({
    mutationFn: () => {
      if (!contract) throw new Error('No contract in progress')
      return updateEmployeeContract(contract.id, { contract_body: bodyDraft, status: 'generated' })
    },
    onSuccess: (updated) => {
      setContract(updated)
      setStep('sign')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const saveDraftMutation = useMutation({
    mutationFn: () => {
      if (!contract) throw new Error('No contract in progress')
      return updateEmployeeContract(contract.id, { contract_body: bodyDraft })
    },
    onSuccess: () => {
      toast.success('Draft saved')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const signMutation = useMutation({
    mutationFn: () => {
      if (!contract) throw new Error('No contract in progress')
      const signatureData = signaturePadRef.current?.toDataUrl()
      if (!signatureData) throw new Error('Please draw a signature first')
      return signEmployeeContract(contract.id, signatureData, signedByName)
    },
    onSuccess: (signed) => {
      setContract(signed)
      setStep('done')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const detailsValid = details.contractType && details.startDate && details.baseSalary

  const close = () => onOpenChange(false)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>
            {step === 'choose' && 'Add Contract'}
            {step === 'details' && 'Generate Contract — Details'}
            {step === 'upload' && 'Upload Signed Copy'}
            {step === 'edit' && 'Edit Contract Text'}
            {step === 'sign' && 'Sign Contract'}
            {step === 'done' && 'Contract Saved'}
          </DialogTitle>
        </DialogHeader>

        {step === 'choose' && (
          <div className="grid grid-cols-2 gap-4 py-2">
            <button
              type="button"
              onClick={() => setStep('upload')}
              className="flex flex-col items-center gap-3 rounded-xl border border-border p-6 text-center hover:border-primary hover:bg-accent"
            >
              <Upload className="h-8 w-8 text-primary" />
              <div>
                <p className="text-sm font-semibold text-foreground">Upload Signed Copy</p>
                <p className="mt-1 text-xs text-muted-foreground">Already have a signed PDF or scan? Upload it directly.</p>
              </div>
            </button>
            <button
              type="button"
              onClick={() => setStep('details')}
              className="flex flex-col items-center gap-3 rounded-xl border border-border p-6 text-center hover:border-primary hover:bg-accent"
            >
              <FileSignature className="h-8 w-8 text-primary" />
              <div>
                <p className="text-sm font-semibold text-foreground">Generate Contract</p>
                <p className="mt-1 text-xs text-muted-foreground">Start from a pre-filled template, edit it, then sign in-system.</p>
              </div>
            </button>
          </div>
        )}

        {(step === 'details' || step === 'upload') && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label>Contract Type</Label>
                <Input value={details.contractType} onChange={(event) => setDetails({ ...details, contractType: event.target.value })} placeholder="Permanent, Fixed-term, etc." />
              </div>
              <div className="space-y-1.5">
                <Label>Currency</Label>
                <Input value={details.currencyCode} onChange={(event) => setDetails({ ...details, currencyCode: event.target.value.toUpperCase() })} maxLength={3} />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label>Start Date</Label>
                <Input type="date" value={details.startDate} onChange={(event) => setDetails({ ...details, startDate: event.target.value })} />
              </div>
              <div className="space-y-1.5">
                <Label>End Date (optional)</Label>
                <Input type="date" value={details.endDate} onChange={(event) => setDetails({ ...details, endDate: event.target.value })} />
              </div>
            </div>
            <div className="space-y-1.5">
              <Label>Base Salary</Label>
              <Input type="number" min="0" step="0.01" value={details.baseSalary} onChange={(event) => setDetails({ ...details, baseSalary: event.target.value })} />
            </div>

            {step === 'upload' && (
              <div className="space-y-1.5 border-t border-border pt-4">
                <Label>Signed Document (PDF or image)</Label>
                <div className="flex items-center gap-2">
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept="application/pdf,image/jpeg,image/png,image/webp"
                    className="hidden"
                    onChange={(event) => setUploadFile(event.target.files?.[0] ?? null)}
                  />
                  <Button type="button" variant="outline" size="sm" onClick={() => fileInputRef.current?.click()}>
                    <Upload className="h-4 w-4" />
                    Choose File
                  </Button>
                  {uploadFile && <span className="truncate text-xs text-muted-foreground">{uploadFile.name}</span>}
                </div>
                <Label className="pt-2">Signed By (optional)</Label>
                <Input value={uploadSignedByName} onChange={(event) => setUploadSignedByName(event.target.value)} placeholder="Name on the physical signature" />
              </div>
            )}
          </div>
        )}

        {step === 'edit' && (
          <div className="space-y-3">
            <p className="text-xs text-muted-foreground">
              Edit the contract text below. This template was pre-filled with {employee.first_name}'s details — adjust the wording as needed before proceeding to sign.
            </p>
            <RichTextEditor value={bodyDraft} onChange={setBodyDraft} disabled={contract?.status === 'signed'} />
          </div>
        )}

        {step === 'sign' && (
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label>Signed By</Label>
              <Input value={signedByName} onChange={(event) => setSignedByName(event.target.value)} placeholder="Full name" />
            </div>
            <div className="space-y-1.5">
              <Label>Signature</Label>
              <SignaturePad ref={signaturePadRef} />
            </div>
          </div>
        )}

        {step === 'done' && contract && (
          <div className="space-y-3 py-4 text-center">
            <p className="text-sm text-foreground">
              {contract.status === 'signed' ? 'The contract has been signed and saved.' : 'The contract has been saved.'}
            </p>
            {contract.document_url && (
              <Button
                variant="outline"
                onClick={() => downloadPdf(`/employee-contracts/${contract.id}/pdf`, `contract-${employee.last_name}.pdf`)}
              >
                View / Download PDF
              </Button>
            )}
          </div>
        )}

        <DialogFooter>
          {step === 'choose' && (
            <Button variant="outline" onClick={close}>
              Cancel
            </Button>
          )}
          {step === 'details' && (
            <>
              <Button variant="outline" onClick={() => setStep('choose')}>
                Back
              </Button>
              <Button disabled={!detailsValid || generateMutation.isPending} onClick={() => generateMutation.mutate()}>
                Generate Template
              </Button>
            </>
          )}
          {step === 'upload' && (
            <>
              <Button variant="outline" onClick={() => setStep('choose')}>
                Back
              </Button>
              <Button disabled={!detailsValid || !uploadFile || uploadMutation.isPending} onClick={() => uploadMutation.mutate()}>
                Upload &amp; Mark Signed
              </Button>
            </>
          )}
          {step === 'edit' && (
            <>
              <Button variant="outline" onClick={close}>
                Close
              </Button>
              <Button variant="outline" disabled={saveDraftMutation.isPending} onClick={() => saveDraftMutation.mutate()}>
                Save Draft
              </Button>
              <Button disabled={!bodyDraft || finalizeMutation.isPending} onClick={() => finalizeMutation.mutate()}>
                Proceed to Sign
              </Button>
            </>
          )}
          {step === 'sign' && (
            <>
              <Button variant="outline" onClick={() => setStep('edit')}>
                Back
              </Button>
              <Button disabled={!signedByName || signMutation.isPending} onClick={() => signMutation.mutate()}>
                Confirm Signature
              </Button>
            </>
          )}
          {step === 'done' && <Button onClick={close}>Done</Button>}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
