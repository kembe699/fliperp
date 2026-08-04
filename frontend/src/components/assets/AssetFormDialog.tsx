import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createAsset, fetchAssetCategories, updateAsset } from '@/api/assets'
import { fetchBranches } from '@/api/branches'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { Asset } from '@/types/assets'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

export function AssetFormDialog({
  open,
  onOpenChange,
  asset,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  asset: Asset | null
}) {
  const queryClient = useQueryClient()
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches, enabled: open })
  const { data: categories } = useQuery({ queryKey: ['asset-categories'], queryFn: fetchAssetCategories, enabled: open })

  const [branchId, setBranchId] = useState('')
  const [categoryId, setCategoryId] = useState('')
  const [assetCode, setAssetCode] = useState('')
  const [name, setName] = useState('')
  const [purchaseDate, setPurchaseDate] = useState('')
  const [purchaseCost, setPurchaseCost] = useState('')

  useEffect(() => {
    if (!open) return
    setBranchId(asset ? String(asset.branch_id) : '')
    setCategoryId(asset ? String(asset.asset_category_id) : '')
    setAssetCode(asset?.asset_code ?? '')
    setName(asset?.name ?? '')
    setPurchaseDate(asset?.purchase_date ?? new Date().toISOString().slice(0, 10))
    setPurchaseCost(asset ? String(asset.purchase_cost) : '')
  }, [open, asset])

  const mutation = useMutation({
    mutationFn: () => {
      const payload = {
        branch_id: Number(branchId),
        asset_category_id: Number(categoryId),
        asset_code: assetCode,
        name,
        purchase_date: purchaseDate,
        purchase_cost: Number(purchaseCost),
      }
      return asset ? updateAsset(asset.id, payload) : createAsset(payload)
    },
    onSuccess: () => {
      toast.success(asset ? 'Asset updated' : 'Asset created')
      queryClient.invalidateQueries({ queryKey: ['assets'] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = branchId && categoryId && assetCode && name && purchaseDate && purchaseCost

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{asset ? 'Edit Asset' : 'New Asset'}</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Asset Code</Label>
              <Input value={assetCode} onChange={(event) => setAssetCode(event.target.value)} placeholder="AST-001" />
            </div>
            <div className="space-y-1.5">
              <Label>Name</Label>
              <Input value={name} onChange={(event) => setName(event.target.value)} />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Branch</Label>
              <Select value={branchId} onValueChange={setBranchId}>
                <SelectTrigger>
                  <SelectValue placeholder="Select branch" />
                </SelectTrigger>
                <SelectContent>
                  {branches?.map((branch) => (
                    <SelectItem key={branch.id} value={String(branch.id)}>
                      {branch.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label>Category</Label>
              <Select value={categoryId} onValueChange={setCategoryId}>
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
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Purchase Date</Label>
              <Input type="date" value={purchaseDate} onChange={(event) => setPurchaseDate(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Purchase Cost</Label>
              <Input type="number" min="0" step="0.01" value={purchaseCost} onChange={(event) => setPurchaseCost(event.target.value)} />
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            {asset ? 'Save Changes' : 'Create Asset'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
