import React, { useEffect, useState } from 'react';
import { useQuery, useMutation } from 'react-query';
import { Box, Grid, Card, CardContent, Typography, Button, Chip, useTheme } from '@mui/material';
import { AccessTime, CheckCircle } from '@mui/icons-material';
import { fetchOrders, updateOrderStatus } from '../api/orders';

const columns = [
  { id: 'pending', title: 'New Orders', color: 'warning' },
  { id: 'preparing', title: 'Preparing', color: 'info' },
  { id: 'ready', title: 'Ready', color: 'success' }
];

export default function KDS() {
  const [audio] = useState(new Audio('/wp-content/plugins/gourmetpress/assets/sounds/order-alert.mp3'));
  const { data: orders, refetch } = useQuery('kds-orders', () => fetchOrders({ status: 'pending,preparing,ready' }), {
    refetchInterval: 10000
  });
  
  const mutation = useMutation(updateOrderStatus, { onSuccess: () => refetch() });
  
  useEffect(() => {
    // Play sound on new orders
    const hasNew = orders?.some(o => o.status === 'pending' && new Date(o.created_at) > new Date(Date.now() - 30000));
    if (hasNew) audio.play();
  }, [orders]);

  const handleStatusChange = (orderId, status) => {
    mutation.mutate({ orderId, status });
  };

  return (
    <Box sx={{ height: '100vh', overflow: 'hidden' }}>
      <Typography variant="h4" gutterBottom>Kitchen Display System</Typography>
      
      <Grid container spacing={2} sx={{ height: 'calc(100% - 60px)' }}>
        {columns.map(col => (
          <Grid item xs={4} key={col.id}>
            <Typography variant="h6" sx={{ mb: 2, color: `${col.color}.main` }}>
              {col.title} ({orders?.filter(o => o.status === col.id).length || 0})
            </Typography>
            
            <Box sx={{ overflowY: 'auto', height: '100%' }}>
              {orders?.filter(o => o.status === col.id).map(order => (
                <OrderCard 
                  key={order.id} 
                  order={order} 
                  onStatusChange={handleStatusChange}
                />
              ))}
            </Box>
          </Grid>
        ))}
      </Grid>
    </Box>
  );
}

function OrderCard({ order, onStatusChange }) {
  const elapsed = Math.floor((Date.now() - new Date(order.created_at)) / 60000);
  
  const nextStatus = {
    'pending': { label: 'Start Preparing', next: 'preparing', color: 'primary' },
    'preparing': { label: 'Mark Ready', next: 'ready', color: 'success' },
    'ready': { label: 'Handed Over', next: 'out_for_delivery', color: 'secondary' }
  }[order.status];

  return (
    <Card sx={{ 
      mb: 2, 
      borderLeft: 4, 
      borderColor: order.status === 'pending' && elapsed > 10 ? 'error.main' : 'primary.main',
      bgcolor: elapsed > 15 ? 'error.light' : 'background.paper'
    }}>
      <CardContent>
        <Box display="flex" justifyContent="space-between" alignItems="center">
          <Typography variant="h6">#{order.order_key}</Typography>
          <Chip 
            icon={<AccessTime />} 
            label={`${elapsed}m`} 
            color={elapsed > 10 ? 'error' : 'default'}
            size="small"
          />
        </Box>
        
        <Typography color="textSecondary" gutterBottom>
          {order.order_type === 'delivery' ? '🚚 Delivery' : '🏃 Pickup'} • {order.items?.length} items
        </Typography>
        
        <Box sx={{ my: 1 }}>
          {order.items?.map((item, idx) => (
            <Typography key={idx} variant="body2">
              <strong>{item.quantity}x</strong> {item.item_name}
              {item.special_instructions && (
                <Typography component="span" color="error" display="block">
                  ⚠️ {item.special_instructions}
                </Typography>
              )}
            </Typography>
          ))}
        </Box>
        
        {order.notes && (
          <Typography sx={{ mt: 1, p: 1, bgcolor: 'warning.light', borderRadius: 1 }} variant="body2">
            📝 {order.notes}
          </Typography>
        )}
        
        <Button
          variant="contained"
          color={nextStatus.color}
          fullWidth
          sx={{ mt: 2 }}
          onClick={() => onStatusChange(order.id, nextStatus.next)}
          startIcon={<CheckCircle />}
        >
          {nextStatus.label}
        </Button>
      </CardContent>
    </Card>
  );
}
