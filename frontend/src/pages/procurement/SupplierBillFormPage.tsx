import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createSupplierBill, fetchSuppliers } from '@/api/procurement'
import { applyFieldErrors, getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/format'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

const billSchema = z.object({
  supplier_id: z.string().min(1, 'Supplier is required'),
  reference_number: z.string().min(1, 'Reference number is required'),
  bill_date: z.string().min(1, 'Bill date is required'),
  due_date: z.string().min(1, 'Due date is required'),
  subtotal: z.number().min(0, 'Must be 0 or more'),
  tax_amount: z.number().min(0, 'Must be 0 or more'),
})

type BillFormSchema = z.infer<typeof billSchema>

export function SupplierBillFormPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data: suppliers } = useQuery({ queryKey: ['suppliers-all'], queryFn: () => fetchSuppliers({ per_page: 100 }) })

  const {
    handleSubmit,
    register,
    control,
    setError,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<BillFormSchema>({
    resolver: zodResolver(billSchema),
    defaultValues: {
      supplier_id: '',
      reference_number: '',
      bill_date: new Date().toISOString().slice(0, 10),
      due_date: new Date().toISOString().slice(0, 10),
      subtotal: 0,
      tax_amount: 0,
    },
  })

  const subtotal = watch('subtotal') || 0
  const taxAmount = watch('tax_amount') || 0

  const mutation = useMutation({
    mutationFn: (values: BillFormSchema) =>
      createSupplierBill({
        supplier_id: Number(values.supplier_id),
        reference_number: values.reference_number,
        bill_date: values.bill_date,
        due_date: values.due_date,
        subtotal: values.subtotal,
        tax_amount: values.tax_amount,
      }),
    onSuccess: (bill) => {
      toast.success('Supplier bill created')
      queryClient.invalidateQueries({ queryKey: ['supplier-bills'] })
      navigate(`/supplier-bills/${bill.id}`)
    },
    onError: (error) => {
      const info = getApiErrorInfo(error)
      if (info.errors) {
        const unmapped = applyFieldErrors(info.errors, setError)
        unmapped.forEach((message) => toast.error(message))
      } else {
        toast.error(info.message)
      }
    },
  })

  return (
    <div>
      <PageHeader parent="Supplier Bills" title="New Bill" />

      <form onSubmit={handleSubmit((values) => mutation.mutate(values))}>
        <Card className="mb-4">
          <CardContent className="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
            <div className="space-y-1.5 sm:col-span-2">
              <Label>Supplier</Label>
              <Controller
                control={control}
                name="supplier_id"
                render={({ field }) => (
                  <SearchableSelect
                    options={(suppliers?.data ?? []).map((supplier) => ({ value: String(supplier.id), label: supplier.name, sublabel: supplier.contact_person ?? undefined }))}
                    value={field.value || null}
                    onChange={(value) => field.onChange(value ?? '')}
                    placeholder="Select supplier"
                  />
                )}
              />
              {errors.supplier_id && <p className="text-xs text-destructive">{errors.supplier_id.message}</p>}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="reference_number">Reference Number</Label>
              <Input id="reference_number" placeholder="e.g. RENT-2026-08" {...register('reference_number')} />
              {errors.reference_number && <p className="text-xs text-destructive">{errors.reference_number.message}</p>}
            </div>

            <div />

            <div className="space-y-1.5">
              <Label htmlFor="bill_date">Bill Date</Label>
              <Input id="bill_date" type="date" {...register('bill_date')} />
              {errors.bill_date && <p className="text-xs text-destructive">{errors.bill_date.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="due_date">Due Date</Label>
              <Input id="due_date" type="date" {...register('due_date')} />
              {errors.due_date && <p className="text-xs text-destructive">{errors.due_date.message}</p>}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="subtotal">Subtotal</Label>
              <Input id="subtotal" type="number" min="0" step="0.01" {...register('subtotal', { valueAsNumber: true })} />
              {errors.subtotal && <p className="text-xs text-destructive">{errors.subtotal.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="tax_amount">Tax Amount</Label>
              <Input id="tax_amount" type="number" min="0" step="0.01" {...register('tax_amount', { valueAsNumber: true })} />
              {errors.tax_amount && <p className="text-xs text-destructive">{errors.tax_amount.message}</p>}
            </div>

            <div className="flex items-center justify-between border-t border-border pt-4 text-base font-bold text-foreground sm:col-span-2">
              <span>Total</span>
              <span>{formatCurrency(subtotal + taxAmount)}</span>
            </div>
          </CardContent>
        </Card>

        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => navigate(-1)}>
            Cancel
          </Button>
          <Button type="submit" disabled={isSubmitting || mutation.isPending}>
            Create Bill
          </Button>
        </div>
      </form>
    </div>
  )
}
