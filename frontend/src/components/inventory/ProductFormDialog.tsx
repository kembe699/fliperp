import { useEffect, useRef, useState } from 'react'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Trash2, Upload, X } from 'lucide-react'

import {
  createProduct,
  createProductVariant,
  deleteProductImage,
  deleteProductVariant,
  fetchCategories,
  fetchUnitsOfMeasure,
  updateProduct,
  uploadProductImage,
} from '@/api/inventory'
import { fetchTaxRates } from '@/api/pos'
import { applyFieldErrors, getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/format'
import { cn } from '@/lib/utils'
import type { Product } from '@/types/inventory'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ProductImage } from '@/components/inventory/ProductImage'

const productSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  sku: z.string().min(1, 'SKU is required'),
  barcode: z.string().optional().or(z.literal('')),
  description: z.string().optional().or(z.literal('')),
  category_id: z.string().min(1, 'Category is required'),
  unit_of_measure_id: z.string().min(1, 'Unit of measure is required'),
  tax_rate_id: z.string().optional(),
  cost_price: z.number().min(0),
  selling_price: z.number().min(0),
  reorder_level: z.number().min(0),
  track_inventory: z.boolean(),
  is_active: z.boolean(),
})

type ProductFormSchema = z.infer<typeof productSchema>

interface ProductFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  product?: Product | null
}

export function ProductFormDialog({ open, onOpenChange, product }: ProductFormDialogProps) {
  const queryClient = useQueryClient()
  const isEdit = !!product

  const { data: categories } = useQuery({ queryKey: ['categories'], queryFn: fetchCategories, enabled: open })
  const { data: units } = useQuery({ queryKey: ['units-of-measure'], queryFn: fetchUnitsOfMeasure, enabled: open })
  const { data: taxRates } = useQuery({ queryKey: ['tax-rates'], queryFn: fetchTaxRates, enabled: open })

  const {
    register,
    handleSubmit,
    control,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<ProductFormSchema>({
    resolver: zodResolver(productSchema),
    defaultValues: {
      name: '', sku: '', barcode: '', description: '', category_id: '', unit_of_measure_id: '',
      tax_rate_id: undefined, cost_price: 0, selling_price: 0, reorder_level: 0, track_inventory: true, is_active: true,
    },
  })

  // Image
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [imageMode, setImageMode] = useState<'upload' | 'url'>('url')
  const [imageFile, setImageFile] = useState<File | null>(null)
  const [imageFilePreview, setImageFilePreview] = useState<string | null>(null)
  const [imageUrlInput, setImageUrlInput] = useState('')
  const [removeExistingImage, setRemoveExistingImage] = useState(false)

  useEffect(() => {
    if (!open) return
    reset({
      name: product?.name ?? '',
      sku: product?.sku ?? '',
      barcode: product?.barcode ?? '',
      description: product?.description ?? '',
      category_id: product ? String(product.category_id) : '',
      unit_of_measure_id: product ? String(product.unit_of_measure_id) : '',
      tax_rate_id: product?.tax_rate_id ? String(product.tax_rate_id) : undefined,
      cost_price: product?.cost_price ?? 0,
      selling_price: product?.selling_price ?? 0,
      reorder_level: product?.reorder_level ?? 0,
      track_inventory: product?.track_inventory ?? true,
      is_active: product?.is_active ?? true,
    })
    setImageMode('url')
    setImageFile(null)
    setImageFilePreview(null)
    setImageUrlInput(product?.image_url ?? '')
    setRemoveExistingImage(false)
  }, [open, product, reset])

  const handleFileSelect = (file: File | null) => {
    setImageFile(file)
    setImageFilePreview(file ? URL.createObjectURL(file) : null)
    setRemoveExistingImage(false)
  }

  const clearImage = () => {
    setImageFile(null)
    setImageFilePreview(null)
    setImageUrlInput('')
    if (fileInputRef.current) fileInputRef.current.value = ''
    setRemoveExistingImage(true)
  }

  const mutation = useMutation({
    mutationFn: async (values: ProductFormSchema) => {
      const payload = {
        name: values.name,
        sku: values.sku,
        barcode: values.barcode || null,
        description: values.description || null,
        category_id: Number(values.category_id),
        unit_of_measure_id: Number(values.unit_of_measure_id),
        tax_rate_id: values.tax_rate_id ? Number(values.tax_rate_id) : null,
        cost_price: values.cost_price,
        selling_price: values.selling_price,
        reorder_level: values.reorder_level,
        track_inventory: values.track_inventory,
        is_active: values.is_active,
        ...(imageMode === 'url' ? { image_url: imageUrlInput || null } : {}),
      }
      const saved = isEdit ? await updateProduct(product!.id, payload) : await createProduct(payload)

      if (imageMode === 'upload' && imageFile) {
        return uploadProductImage(saved.id, imageFile)
      }
      if (imageMode === 'upload' && removeExistingImage && isEdit) {
        return deleteProductImage(saved.id)
      }

      return saved
    },
    onSuccess: () => {
      toast.success(isEdit ? 'Product updated' : 'Product created')
      queryClient.invalidateQueries({ queryKey: ['products'] })
      onOpenChange(false)
    },
    onError: (error) => {
      const info = getApiErrorInfo(error)
      if (info.errors) {
        applyFieldErrors(info.errors, setError).forEach((message) => toast.error(message))
      } else {
        toast.error(info.message)
      }
    },
  })

  // Variants
  const queryClientForVariants = useQueryClient()
  const [variantName, setVariantName] = useState('')
  const [variantSku, setVariantSku] = useState('')
  const [variantAdjustment, setVariantAdjustment] = useState('0')

  const addVariantMutation = useMutation({
    mutationFn: () =>
      createProductVariant(product!.id, { name: variantName, sku: variantSku, price_adjustment: Number(variantAdjustment) || 0 }),
    onSuccess: () => {
      toast.success('Variant added')
      setVariantName('')
      setVariantSku('')
      setVariantAdjustment('0')
      queryClientForVariants.invalidateQueries({ queryKey: ['products'] })
      queryClientForVariants.invalidateQueries({ queryKey: ['product', product?.id] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const removeVariantMutation = useMutation({
    mutationFn: (variantId: number) => deleteProductVariant(product!.id, variantId),
    onSuccess: () => {
      toast.success('Variant removed')
      queryClientForVariants.invalidateQueries({ queryKey: ['products'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'Edit Product' : 'New Product'}</DialogTitle>
        </DialogHeader>

        <form onSubmit={handleSubmit((values) => mutation.mutate(values))} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="name">Name</Label>
              <Input id="name" {...register('name')} />
              {errors.name && <p className="text-xs text-destructive">{errors.name.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="sku">SKU</Label>
              <Input id="sku" {...register('sku')} />
              {errors.sku && <p className="text-xs text-destructive">{errors.sku.message}</p>}
            </div>
          </div>

          <div className="space-y-2">
            <Label>Product Image</Label>
            <div className="flex items-start gap-4">
              <ProductImage
                src={removeExistingImage ? null : imageMode === 'upload' ? (imageFilePreview ?? product?.image_url) : imageUrlInput}
                alt={product?.name ?? 'Product'}
                className="h-20 w-20"
              />
              <div className="flex-1 space-y-2">
                <div className="flex gap-1.5">
                  <Button
                    type="button"
                    size="sm"
                    variant={imageMode === 'url' ? 'default' : 'outline'}
                    onClick={() => setImageMode('url')}
                  >
                    Image URL
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant={imageMode === 'upload' ? 'default' : 'outline'}
                    onClick={() => setImageMode('upload')}
                  >
                    Upload Image
                  </Button>
                </div>

                {imageMode === 'url' ? (
                  <Input
                    type="url"
                    placeholder="https://example.com/photo.jpg"
                    value={imageUrlInput}
                    onChange={(event) => {
                      setImageUrlInput(event.target.value)
                      setRemoveExistingImage(false)
                    }}
                  />
                ) : (
                  <div className="flex items-center gap-2">
                    <input
                      ref={fileInputRef}
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      className="hidden"
                      onChange={(event) => handleFileSelect(event.target.files?.[0] ?? null)}
                    />
                    <Button type="button" variant="outline" size="sm" onClick={() => fileInputRef.current?.click()}>
                      <Upload className="h-4 w-4" />
                      Choose File
                    </Button>
                    {imageFile && <span className="truncate text-xs text-muted-foreground">{imageFile.name}</span>}
                    <span className="text-xs text-muted-foreground">JPG, PNG or WEBP, max 2MB</span>
                  </div>
                )}

                {!removeExistingImage && (imageFilePreview || imageUrlInput || product?.image_url) && (
                  <button
                    type="button"
                    onClick={clearImage}
                    className={cn('flex items-center gap-1 text-xs text-muted-foreground hover:text-destructive')}
                  >
                    <X className="h-3 w-3" />
                    Remove image
                  </button>
                )}
              </div>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="barcode">Barcode</Label>
              <Input id="barcode" {...register('barcode')} />
            </div>
            <div className="space-y-1.5">
              <Label>Category</Label>
              <Controller
                control={control}
                name="category_id"
                render={({ field }) => (
                  <Select value={field.value} onValueChange={field.onChange}>
                    <SelectTrigger>
                      <SelectValue placeholder="Select category" />
                    </SelectTrigger>
                    <SelectContent>
                      {categories?.map((category) => (
                        <SelectItem key={category.id} value={String(category.id)}>
                          {category.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              />
              {errors.category_id && <p className="text-xs text-destructive">{errors.category_id.message}</p>}
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="description">Description</Label>
            <Textarea id="description" {...register('description')} />
          </div>

          <div className="grid grid-cols-3 gap-4">
            <div className="space-y-1.5">
              <Label>Unit of Measure</Label>
              <Controller
                control={control}
                name="unit_of_measure_id"
                render={({ field }) => (
                  <Select value={field.value} onValueChange={field.onChange}>
                    <SelectTrigger>
                      <SelectValue placeholder="Select unit" />
                    </SelectTrigger>
                    <SelectContent>
                      {units?.map((unit) => (
                        <SelectItem key={unit.id} value={String(unit.id)}>
                          {unit.name} ({unit.abbreviation})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              />
              {errors.unit_of_measure_id && <p className="text-xs text-destructive">{errors.unit_of_measure_id.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label>Tax Rate</Label>
              <Controller
                control={control}
                name="tax_rate_id"
                render={({ field }) => (
                  <Select value={field.value ?? 'none'} onValueChange={(value) => field.onChange(value === 'none' ? undefined : value)}>
                    <SelectTrigger>
                      <SelectValue placeholder="None" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">None</SelectItem>
                      {taxRates?.map((rate) => (
                        <SelectItem key={rate.id} value={String(rate.id)}>
                          {rate.name} ({Number(rate.rate)}%)
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="reorder_level">Reorder Level</Label>
              <Input id="reorder_level" type="number" step="0.01" min="0" {...register('reorder_level', { valueAsNumber: true })} />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="cost_price">Cost Price</Label>
              <Input id="cost_price" type="number" step="0.01" min="0" {...register('cost_price', { valueAsNumber: true })} />
              {errors.cost_price && <p className="text-xs text-destructive">{errors.cost_price.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="selling_price">Selling Price</Label>
              <Input id="selling_price" type="number" step="0.01" min="0" {...register('selling_price', { valueAsNumber: true })} />
              {errors.selling_price && <p className="text-xs text-destructive">{errors.selling_price.message}</p>}
            </div>
          </div>

          <div className="flex items-center gap-6">
            <label className="flex items-center gap-2 text-sm text-foreground">
              <input type="checkbox" {...register('track_inventory')} className="h-4 w-4 rounded border-input" />
              Track Inventory
            </label>
            <label className="flex items-center gap-2 text-sm text-foreground">
              <input type="checkbox" {...register('is_active')} className="h-4 w-4 rounded border-input" />
              Active
            </label>
          </div>

          {isEdit && (
            <div className="rounded-xl border border-border p-4">
              <p className="mb-2 text-sm font-semibold text-foreground">Variants</p>
              {product?.variants && product.variants.length > 0 && (
                <ul className="mb-3 space-y-1.5">
                  {product.variants.map((variant) => (
                    <li key={variant.id} className="flex items-center justify-between rounded-lg bg-muted px-3 py-1.5 text-sm">
                      <span>
                        {variant.name} · {variant.sku} · {formatCurrency(variant.price_adjustment)}
                      </span>
                      <button
                        type="button"
                        onClick={() => removeVariantMutation.mutate(variant.id)}
                        className="text-muted-foreground hover:text-destructive"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    </li>
                  ))}
                </ul>
              )}
              <div className="flex items-end gap-2">
                <Input placeholder="Name" value={variantName} onChange={(event) => setVariantName(event.target.value)} className="h-9" />
                <Input placeholder="SKU" value={variantSku} onChange={(event) => setVariantSku(event.target.value)} className="h-9" />
                <Input
                  placeholder="Price adj."
                  type="number"
                  step="0.01"
                  value={variantAdjustment}
                  onChange={(event) => setVariantAdjustment(event.target.value)}
                  className="h-9 w-28"
                />
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={!variantName || !variantSku || addVariantMutation.isPending}
                  onClick={() => addVariantMutation.mutate()}
                >
                  <Plus className="h-4 w-4" />
                </Button>
              </div>
            </div>
          )}
          {!isEdit && <p className="text-xs text-muted-foreground">Save the product first to add variants.</p>}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isEdit ? 'Save Changes' : 'Create Product'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
